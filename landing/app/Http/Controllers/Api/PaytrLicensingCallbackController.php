<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaasCheckoutOrder;
use App\Services\Licensing\LicenseRetailFulfillmentService;
use App\Services\Licensing\PaytrLicensingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaytrLicensingCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        PaytrLicensingService $paytr,
        LicenseRetailFulfillmentService $fulfillment,
    ): Response {
        $post = $request->request->all();
        if (! is_array($post) || $post === []) {
            return response('bad', 400);
        }

        if (! $paytr->verifyCallbackHash($post)) {
            Log::warning('PayTR callback bad hash', ['merchant_oid' => $post['merchant_oid'] ?? '']);

            return response('bad hash', 400);
        }

        $oid = (string) ($post['merchant_oid'] ?? '');
        $status = (string) ($post['status'] ?? '');

        $order = SaasCheckoutOrder::query()
            ->where('order_ref', $oid)
            ->where('provider', 'paytr')
            ->first();

        if (! $order) {
            return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if ($status !== 'success') {
            $order->update([
                'status' => 'failed',
                'failure_note' => substr($status.' '.($post['failed_reason_msg'] ?? ''), 0, 500),
            ]);

            return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $paymentAmount = (string) ($post['payment_amount'] ?? '');
        if ($paymentAmount !== (string) $order->amount_minor) {
            Log::warning('PayTR amount mismatch', [
                'order_ref' => $oid,
                'expected' => $order->amount_minor,
                'got' => $paymentAmount,
            ]);

            return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $fulfillment->fulfillIfPending($order->fresh(), $oid);

        return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
