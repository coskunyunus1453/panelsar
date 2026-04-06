<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use App\Models\SaasCheckoutOrder;
use App\Models\SaasLicenseProduct;
use App\Services\Licensing\BillingProviderResolver;
use App\Services\Licensing\PaytrLicensingService;
use App\Services\Licensing\StripeLicensingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class LicensingCheckoutController extends Controller
{
    public function start(
        Request $request,
        BillingProviderResolver $resolver,
        PaytrLicensingService $paytr,
        StripeLicensingService $stripe,
    ): JsonResponse {
        $validated = $request->validate([
            'product_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'billing' => ['nullable', 'string', 'in:auto,paytr,stripe,bank_transfer'],
        ]);

        $product = SaasLicenseProduct::query()
            ->where('code', $validated['product_code'])
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found or inactive.'], 422);
        }

        $billingInput = $validated['billing'] ?? null;
        if ($billingInput === 'auto') {
            $billingInput = null;
        }

        $provider = $resolver->resolve($billingInput, app()->getLocale());

        if ($provider === 'paytr') {
            if ($product->price_try_minor === null || (int) $product->price_try_minor <= 0) {
                return response()->json(['message' => 'TRY price not configured for this product (admin: price_try_minor).'], 422);
            }
            if (! $paytr->isConfigured()) {
                return response()->json(['message' => 'PayTR is not configured (PAYTR_* env).'], 503);
            }
            $amountMinor = (int) $product->price_try_minor;
            $currency = 'TRY';
        } elseif ($provider === 'stripe') {
            if ($product->price_usd_minor === null || (int) $product->price_usd_minor <= 0) {
                return response()->json(['message' => 'USD price not configured for this product (admin: price_usd_minor).'], 422);
            }
            if (! $stripe->isConfigured()) {
                return response()->json(['message' => 'Stripe is not configured (STRIPE_SECRET).'], 503);
            }
            $amountMinor = (int) $product->price_usd_minor;
            $currency = 'USD';
        } else {
            $bankEnabled = trim((string) (LandingSiteSetting::getValue('billing.methods.bank_transfer.enabled', '0') ?? '0')) === '1';
            if (! $bankEnabled) {
                return response()->json(['message' => 'Bank transfer is not enabled.'], 422);
            }
            if ($product->price_try_minor !== null && (int) $product->price_try_minor > 0) {
                $amountMinor = (int) $product->price_try_minor;
                $currency = 'TRY';
            } elseif ($product->price_usd_minor !== null && (int) $product->price_usd_minor > 0) {
                $amountMinor = (int) $product->price_usd_minor;
                $currency = 'USD';
            } else {
                return response()->json(['message' => 'No configured product price for bank transfer.'], 422);
            }
        }

        $orderRef = 'hv'.Str::lower(Str::random(22));

        $order = SaasCheckoutOrder::query()->create([
            'order_ref' => $orderRef,
            'provider' => $provider,
            'locale' => substr((string) app()->getLocale(), 0, 10) ?: 'en',
            'email' => strtolower(trim($validated['email'])),
            'name' => $validated['name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'saas_license_product_id' => $product->id,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'status' => 'pending',
        ]);

        if ($provider === 'bank_transfer') {
            $order->update(['status' => 'awaiting_transfer']);

            return response()->json([
                'provider' => 'bank_transfer',
                'order_ref' => $orderRef,
                'status' => 'awaiting_transfer',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'bank' => [
                    'account_name' => LandingSiteSetting::getValue('billing.bank_transfer.account_name', ''),
                    'bank_name' => LandingSiteSetting::getValue('billing.bank_transfer.bank_name', ''),
                    'branch' => LandingSiteSetting::getValue('billing.bank_transfer.branch', ''),
                    'iban' => LandingSiteSetting::getValue('billing.bank_transfer.iban', ''),
                    'instructions' => LandingSiteSetting::getValue('billing.bank_transfer.instructions', ''),
                ],
            ]);
        }

        try {
            if ($provider === 'stripe') {
                $session = $stripe->createCheckoutSession($order, $product);
                $order->update(['stripe_checkout_session_id' => $session->id]);

                return response()->json([
                    'provider' => 'stripe',
                    'order_ref' => $orderRef,
                    'checkout_url' => $session->url,
                ]);
            }

            $token = $paytr->createIframeToken($order, $product);

            return response()->json([
                'provider' => 'paytr',
                'order_ref' => $orderRef,
                'iframe_token' => $token['token'],
                'iframe_url' => 'https://www.paytr.com/odeme/guvenli/'.$token['token'],
            ]);
        } catch (RuntimeException $e) {
            $order->update([
                'status' => 'failed',
                'failure_note' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Payment provider error: '.$e->getMessage()], 502);
        }
    }

    public function orderStatus(Request $request, string $orderRef): JsonResponse
    {
        if (! preg_match('/^hv[a-zA-Z0-9]{20,40}$/', $orderRef)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $order = SaasCheckoutOrder::query()
            ->where('order_ref', $orderRef)
            ->with(['license', 'product'])
            ->first();

        if (! $order || ! hash_equals(strtolower($order->email), strtolower($validated['email']))) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $payload = [
            'order_ref' => $order->order_ref,
            'status' => $order->status,
            'provider' => $order->provider,
            'product' => $order->product?->code,
        ];

        if ($order->status === 'completed' && $order->license) {
            $payload['license_key'] = $order->license->license_key;
        }

        return response()->json($payload);
    }
}
