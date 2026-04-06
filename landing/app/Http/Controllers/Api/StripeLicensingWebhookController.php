<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use App\Models\SaasCheckoutOrder;
use App\Services\Licensing\LicenseRetailFulfillmentService;
use App\Services\Licensing\StripeLicensingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeLicensingWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeLicensingService $stripe,
        LicenseRetailFulfillmentService $fulfillment,
    ): Response {
        $secret = trim((string) (LandingSiteSetting::getValue('billing.stripe.webhook_secret', (string) config('hostvim_saas.stripe.webhook_secret', '')) ?? ''));
        if ($secret === '') {
            return response('webhook secret not configured', 503);
        }

        $payload = $request->getContent();
        $sig = (string) $request->header('Stripe-Signature', '');

        $event = $stripe->parseWebhookEvent($payload, $sig);
        if ($event === null) {
            return response('invalid signature', 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response('ignored', 200);
        }

        $session = $event->data->object;
        if (! is_object($session)) {
            return response('ignored', 200);
        }

        if (($session->mode ?? '') !== 'payment') {
            return response('ignored', 200);
        }

        $orderRef = '';
        if (isset($session->metadata) && isset($session->metadata['order_ref'])) {
            $orderRef = (string) $session->metadata['order_ref'];
        }

        if ($orderRef === '') {
            Log::warning('Stripe licensing webhook missing order_ref');

            return response('no order_ref', 200);
        }

        $order = SaasCheckoutOrder::query()
            ->where('order_ref', $orderRef)
            ->where('provider', 'stripe')
            ->first();

        if (! $order) {
            return response('OK', 200);
        }

        $total = (int) ($session->amount_total ?? 0);
        if ($total > 0 && $total !== (int) $order->amount_minor) {
            Log::warning('Stripe licensing amount mismatch', [
                'order_ref' => $orderRef,
                'expected' => $order->amount_minor,
                'got' => $total,
            ]);

            return response('OK', 200);
        }

        $ref = (string) ($session->id ?? '');
        if ($ref === '') {
            return response('OK', 200);
        }

        $fulfillment->fulfillIfPending($order->fresh(), $ref);

        return response('OK', 200);
    }
}
