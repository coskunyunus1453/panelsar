<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HostingPackage;
use App\Models\Subscription as PanelSubscription;
use App\Models\User;
use App\Services\UserHostingPackageSync;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException as StripeUnexpectedValueException;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

class BillingController extends Controller
{
    public function __construct(
        private UserHostingPackageSync $hostingPackageSync,
    ) {}

    public function packages(): JsonResponse
    {
        return response()->json([
            'packages' => HostingPackage::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $subs = $request->user()->subscriptions()->with('hostingPackage')->latest()->paginate(20);

        return response()->json($subs);
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:hosting_packages,id',
            'billing_cycle' => 'required|string|in:monthly,yearly',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        $secret = config('services.stripe.secret');
        if (! $secret) {
            return response()->json([
                'message' => __('billing.stripe_not_configured'),
                'demo' => true,
                'package_id' => $validated['package_id'],
            ], 422);
        }

        $package = HostingPackage::findOrFail($validated['package_id']);
        $amount = $validated['billing_cycle'] === 'yearly' ? $package->price_yearly : $package->price_monthly;
        $stripe = new StripeClient($secret);

        $user = $request->user();
        $meta = [
            'user_id' => (string) $user->id,
            'hosting_package_id' => (string) $package->id,
            'billing_cycle' => $validated['billing_cycle'],
        ];

        $session = $stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $user->email,
            'client_reference_id' => (string) $user->id,
            'metadata' => $meta,
            'subscription_data' => [
                'metadata' => $meta,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($package->currency),
                    'product_data' => ['name' => $package->name],
                    'unit_amount' => (int) round($amount * 100),
                    'recurring' => ['interval' => $validated['billing_cycle'] === 'yearly' ? 'year' : 'month'],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $validated['success_url'] ?? url('/billing/success'),
            'cancel_url' => $validated['cancel_url'] ?? url('/billing/cancel'),
        ]);

        return response()->json(['url' => $session->url, 'id' => $session->id]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            return response()->json(['message' => __('billing.webhook_not_configured')], 503);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (StripeUnexpectedValueException) {
            return response()->json(['message' => __('billing.webhook_invalid_payload')], 400);
        } catch (SignatureVerificationException) {
            return response()->json(['message' => __('billing.webhook_invalid_signature')], 400);
        }

        match ($event->type) {
            'customer.subscription.created',
            'customer.subscription.updated' => $this->syncSubscriptionFromStripeEvent($event),
            'customer.subscription.deleted' => $this->syncSubscriptionFromStripeEvent($event),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function syncSubscriptionFromStripeEvent(Event $event): void
    {
        $stripeSub = $event->data->object;
        if (! $stripeSub instanceof StripeSubscription) {
            return;
        }

        $this->syncPanelSubscription($stripeSub);
    }

    private function syncPanelSubscription(StripeSubscription $stripe): void
    {
        $existing = PanelSubscription::query()
            ->where('stripe_subscription_id', $stripe->id)
            ->first();

        $userId = $this->stripeMetaInt($stripe->metadata, 'user_id') ?? $existing?->user_id;
        $packageId = $this->stripeMetaInt($stripe->metadata, 'hosting_package_id') ?? $existing?->hosting_package_id;

        if ($userId === null || $packageId === null) {
            Log::warning('Stripe subscription webhook skipped: missing user_id or hosting_package_id', [
                'stripe_subscription_id' => $stripe->id,
            ]);

            return;
        }

        if (! User::query()->whereKey($userId)->exists() || ! HostingPackage::query()->whereKey($packageId)->exists()) {
            Log::warning('Stripe subscription webhook skipped: invalid user or package', [
                'stripe_subscription_id' => $stripe->id,
                'user_id' => $userId,
                'hosting_package_id' => $packageId,
            ]);

            return;
        }

        $billingCycle = $this->stripeMetaString($stripe->metadata, 'billing_cycle');
        $price = $stripe->items->data[0]->price ?? null;
        if ($billingCycle !== 'yearly' && $billingCycle !== 'monthly' && $price?->recurring) {
            $billingCycle = ($price->recurring->interval ?? 'month') === 'year' ? 'yearly' : 'monthly';
        }
        if ($billingCycle !== 'yearly' && $billingCycle !== 'monthly') {
            $billingCycle = 'monthly';
        }

        $amount = 0.0;
        $currency = strtoupper($stripe->currency ?? 'USD');
        if ($price && $price->unit_amount !== null) {
            $amount = round($price->unit_amount / 100, 2);
            $currency = strtoupper($price->currency ?? $currency);
        }

        $startsAt = $stripe->start_date
            ? Carbon::createFromTimestamp($stripe->start_date)
            : ($stripe->current_period_start
                ? Carbon::createFromTimestamp($stripe->current_period_start)
                : now());

        $endsAt = null;
        if ($stripe->status === StripeSubscription::STATUS_CANCELED && $stripe->ended_at) {
            $endsAt = Carbon::createFromTimestamp($stripe->ended_at);
        }

        $cancelledAt = $stripe->canceled_at
            ? Carbon::createFromTimestamp($stripe->canceled_at)
            : null;

        $trialEndsAt = $stripe->trial_end
            ? Carbon::createFromTimestamp($stripe->trial_end)
            : null;

        PanelSubscription::query()->updateOrCreate(
            ['stripe_subscription_id' => $stripe->id],
            [
                'user_id' => $userId,
                'hosting_package_id' => $packageId,
                'payment_provider' => 'stripe',
                'status' => $stripe->status,
                'billing_cycle' => $billingCycle,
                'amount' => $amount,
                'currency' => $currency,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'cancelled_at' => $cancelledAt,
                'trial_ends_at' => $trialEndsAt,
            ],
        );

        $this->hostingPackageSync->syncFromSubscriptions($userId);
    }

    private function stripeMetaInt(?\Stripe\StripeObject $metadata, string $key): ?int
    {
        $raw = $this->stripeMetaString($metadata, $key);
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function stripeMetaString(?\Stripe\StripeObject $metadata, string $key): ?string
    {
        if ($metadata === null || ! isset($metadata[$key])) {
            return null;
        }

        $value = $metadata[$key];

        return $value === null || $value === '' ? null : (string) $value;
    }
}
