<?php

namespace App\Services\Licensing;

use App\Models\LandingSiteSetting;
use App\Models\SaasCheckoutOrder;
use App\Models\SaasLicenseProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaytrLicensingService
{
    public function isConfigured(): bool
    {
        $id = $this->setting('billing.paytr.merchant_id', (string) config('hostvim_saas.paytr.merchant_id', ''));
        $key = $this->setting('billing.paytr.merchant_key', (string) config('hostvim_saas.paytr.merchant_key', ''));
        $salt = $this->setting('billing.paytr.merchant_salt', (string) config('hostvim_saas.paytr.merchant_salt', ''));

        return $id !== '' && $key !== '' && $salt !== '';
    }

    /**
     * @return array{token: string}
     */
    public function createIframeToken(SaasCheckoutOrder $order, SaasLicenseProduct $product): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('paytr_not_configured');
        }

        $merchantId = $this->setting('billing.paytr.merchant_id', (string) config('hostvim_saas.paytr.merchant_id', ''));
        $merchantKey = $this->setting('billing.paytr.merchant_key', (string) config('hostvim_saas.paytr.merchant_key', ''));
        $merchantSalt = $this->setting('billing.paytr.merchant_salt', (string) config('hostvim_saas.paytr.merchant_salt', ''));
        $testMode = $this->setting('billing.paytr.test_mode', (string) config('hostvim_saas.paytr.test_mode', '0'));
        $debugOn = $this->setting('billing.paytr.debug_on', (string) config('hostvim_saas.paytr.debug_on', '0'));
        $timeoutSetting = $this->setting('billing.paytr.timeout_minutes', (string) config('hostvim_saas.paytr.timeout_limit', 30));
        $timeoutLimit = (string) max(1, (int) $timeoutSetting);

        $userIp = request()->ip();
        if (! is_string($userIp) || $userIp === '' || $userIp === '127.0.0.1') {
            $userIp = '8.8.8.8';
        }
        $userIp = substr($userIp, 0, 39);

        $priceStr = number_format($order->amount_minor / 100, 2, '.', '');
        $basket = base64_encode(json_encode([
            [$product->name, $priceStr, 1],
        ], JSON_UNESCAPED_UNICODE));

        $email = substr($order->email, 0, 100);
        $noInstallment = '1';
        $maxInstallment = '0';
        $currency = 'TL';
        $userName = substr(trim((string) ($order->name ?: $order->email)), 0, 60);
        $userAddress = '-';
        $userPhone = substr(preg_replace('/\D+/', '', (string) ($order->phone ?: '05000000000')) ?: '05000000000', 0, 20);

        $okUrl = url('/license/success?ref='.urlencode($order->order_ref));
        $failUrl = url('/license/cancel?ref='.urlencode($order->order_ref));

        $hashStr = $merchantId.$userIp.$order->order_ref.$email.$order->amount_minor.$basket.$noInstallment.$maxInstallment.$currency.$testMode;
        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr.$merchantSalt, $merchantKey, true));

        $post = [
            'merchant_id' => $merchantId,
            'user_ip' => $userIp,
            'merchant_oid' => $order->order_ref,
            'email' => $email,
            'payment_amount' => (string) $order->amount_minor,
            'paytr_token' => $paytrToken,
            'user_basket' => $basket,
            'debug_on' => $debugOn,
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'user_name' => $userName,
            'user_address' => $userAddress,
            'user_phone' => $userPhone,
            'merchant_ok_url' => $okUrl,
            'merchant_fail_url' => $failUrl,
            'timeout_limit' => $timeoutLimit,
            'currency' => $currency,
            'test_mode' => $testMode,
            'lang' => $order->locale === 'tr' ? 'tr' : 'en',
        ];

        $response = Http::asForm()
            ->timeout(25)
            ->post('https://www.paytr.com/odeme/api/get-token', $post);

        if (! $response->successful()) {
            Log::warning('PayTR get-token HTTP error', ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);
            throw new RuntimeException('paytr_http_error');
        }

        $json = $response->json();
        if (! is_array($json) || ($json['status'] ?? '') !== 'success' || empty($json['token'])) {
            Log::warning('PayTR get-token failed', ['body' => Str::limit($response->body(), 500)]);
            throw new RuntimeException('paytr_token_failed: '.(is_array($json) ? (string) ($json['reason'] ?? '') : ''));
        }

        return ['token' => (string) $json['token']];
    }

    /**
     * @param  array<string, string>  $post
     */
    public function verifyCallbackHash(array $post): bool
    {
        $merchantKey = $this->setting('billing.paytr.merchant_key', (string) config('hostvim_saas.paytr.merchant_key', ''));
        $merchantSalt = $this->setting('billing.paytr.merchant_salt', (string) config('hostvim_saas.paytr.merchant_salt', ''));

        $oid = (string) ($post['merchant_oid'] ?? '');
        $status = (string) ($post['status'] ?? '');
        $total = (string) ($post['total_amount'] ?? '');
        $hash = (string) ($post['hash'] ?? '');

        if ($oid === '' || $hash === '') {
            return false;
        }

        $calc = base64_encode(hash_hmac('sha256', $oid.$merchantSalt.$status.$total, $merchantKey, true));

        return hash_equals($calc, $hash);
    }

    private function setting(string $key, string $default): string
    {
        return trim((string) (LandingSiteSetting::getValue($key, $default) ?? $default));
    }
}
