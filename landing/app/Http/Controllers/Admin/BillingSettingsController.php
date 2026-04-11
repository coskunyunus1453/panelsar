<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.billing-settings.edit', [
            'paytrEnabled' => $this->settingBool('billing.methods.paytr.enabled', true),
            'stripeEnabled' => $this->settingBool('billing.methods.stripe.enabled', true),
            'bankTransferEnabled' => $this->settingBool('billing.methods.bank_transfer.enabled', false),
            'defaultProvider' => $this->settingString('billing.default_provider', 'auto'),
            'forceProvider' => $this->settingString('billing.force_provider', ''),
            'trLocales' => $this->settingString('billing.tr_locales', 'tr'),
            'paytrMerchantId' => $this->settingString('billing.paytr.merchant_id', ''),
            'paytrMerchantKey' => $this->settingString('billing.paytr.merchant_key', ''),
            'paytrMerchantSalt' => $this->settingString('billing.paytr.merchant_salt', ''),
            'paytrTestMode' => $this->settingString('billing.paytr.test_mode', '0'),
            'paytrDebugOn' => $this->settingString('billing.paytr.debug_on', '0'),
            'paytrTimeoutMinutes' => $this->settingString('billing.paytr.timeout_minutes', '30'),
            'stripeSecret' => $this->settingString('billing.stripe.secret', ''),
            'stripeWebhookSecret' => $this->settingString('billing.stripe.webhook_secret', ''),
            'bankAccountName' => $this->settingString('billing.bank_transfer.account_name', ''),
            'bankName' => $this->settingString('billing.bank_transfer.bank_name', ''),
            'bankBranch' => $this->settingString('billing.bank_transfer.branch', ''),
            'bankIban' => $this->settingString('billing.bank_transfer.iban', ''),
            'bankInstructions' => $this->settingString('billing.bank_transfer.instructions', ''),
            'salesDisplayCurrency' => $this->settingString('billing.sales.display_currency', 'TRY'),
            'stripeCheckoutCurrency' => $this->settingString('billing.stripe.checkout_currency', 'usd'),
            'fxTryPerUsd' => $this->settingString('billing.fx.try_per_usd', '35'),
            'fxTryPerEur' => $this->settingString('billing.fx.try_per_eur', '38'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'paytr_enabled' => ['nullable', 'boolean'],
            'stripe_enabled' => ['nullable', 'boolean'],
            'bank_transfer_enabled' => ['nullable', 'boolean'],
            'default_provider' => ['required', 'in:auto,paytr,stripe,bank_transfer'],
            'force_provider' => ['nullable', 'in:,paytr,stripe,bank_transfer'],
            'tr_locales' => ['nullable', 'string', 'max:100'],
            'paytr_merchant_id' => ['nullable', 'string', 'max:120'],
            'paytr_merchant_key' => ['nullable', 'string', 'max:255'],
            'paytr_merchant_salt' => ['nullable', 'string', 'max:255'],
            'paytr_test_mode' => ['nullable', 'in:0,1'],
            'paytr_debug_on' => ['nullable', 'in:0,1'],
            'paytr_timeout_minutes' => ['nullable', 'integer', 'min:1', 'max:120'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_iban' => ['nullable', 'string', 'max:64'],
            'bank_instructions' => ['nullable', 'string', 'max:4000'],
            'sales_display_currency' => ['required', 'in:TRY,USD,EUR'],
            'stripe_checkout_currency' => ['required', 'in:usd,eur'],
            'fx_try_per_usd' => ['required', 'numeric', 'min:0.0001', 'max:999999'],
            'fx_try_per_eur' => ['required', 'numeric', 'min:0.0001', 'max:999999'],
        ]);

        LandingSiteSetting::put('billing.methods.paytr.enabled', $request->boolean('paytr_enabled') ? '1' : '0');
        LandingSiteSetting::put('billing.methods.stripe.enabled', $request->boolean('stripe_enabled') ? '1' : '0');
        LandingSiteSetting::put('billing.methods.bank_transfer.enabled', $request->boolean('bank_transfer_enabled') ? '1' : '0');
        LandingSiteSetting::put('billing.default_provider', trim((string) ($validated['default_provider'] ?? 'auto')));
        LandingSiteSetting::put('billing.force_provider', trim((string) ($validated['force_provider'] ?? '')));
        LandingSiteSetting::put('billing.tr_locales', trim((string) ($validated['tr_locales'] ?? 'tr')));

        LandingSiteSetting::put('billing.paytr.merchant_id', trim((string) ($validated['paytr_merchant_id'] ?? '')));
        LandingSiteSetting::put('billing.paytr.merchant_key', trim((string) ($validated['paytr_merchant_key'] ?? '')));
        LandingSiteSetting::put('billing.paytr.merchant_salt', trim((string) ($validated['paytr_merchant_salt'] ?? '')));
        LandingSiteSetting::put('billing.paytr.test_mode', trim((string) ($validated['paytr_test_mode'] ?? '0')));
        LandingSiteSetting::put('billing.paytr.debug_on', trim((string) ($validated['paytr_debug_on'] ?? '0')));
        LandingSiteSetting::put('billing.paytr.timeout_minutes', (string) ((int) ($validated['paytr_timeout_minutes'] ?? 30)));

        LandingSiteSetting::put('billing.stripe.secret', trim((string) ($validated['stripe_secret'] ?? '')));
        LandingSiteSetting::put('billing.stripe.webhook_secret', trim((string) ($validated['stripe_webhook_secret'] ?? '')));

        LandingSiteSetting::put('billing.bank_transfer.account_name', trim((string) ($validated['bank_account_name'] ?? '')));
        LandingSiteSetting::put('billing.bank_transfer.bank_name', trim((string) ($validated['bank_name'] ?? '')));
        LandingSiteSetting::put('billing.bank_transfer.branch', trim((string) ($validated['bank_branch'] ?? '')));
        LandingSiteSetting::put('billing.bank_transfer.iban', preg_replace('/\s+/', '', trim((string) ($validated['bank_iban'] ?? ''))) ?: '');
        LandingSiteSetting::put('billing.bank_transfer.instructions', trim((string) ($validated['bank_instructions'] ?? '')));

        LandingSiteSetting::put('billing.sales.display_currency', strtoupper(trim((string) ($validated['sales_display_currency'] ?? 'TRY'))));
        LandingSiteSetting::put('billing.stripe.checkout_currency', strtolower(trim((string) ($validated['stripe_checkout_currency'] ?? 'usd'))));
        LandingSiteSetting::put('billing.fx.try_per_usd', $this->normalizeFxInput($validated['fx_try_per_usd'] ?? '35'));
        LandingSiteSetting::put('billing.fx.try_per_eur', $this->normalizeFxInput($validated['fx_try_per_eur'] ?? '38'));

        return redirect()->route('admin.billing-settings.edit')->with('status', 'Odeme yontemleri ayarlari kaydedildi.');
    }

    private function settingString(string $key, string $default = ''): string
    {
        return trim((string) (LandingSiteSetting::getValue($key, $default) ?? $default));
    }

    private function settingBool(string $key, bool $default): bool
    {
        $fallback = $default ? '1' : '0';

        return $this->settingString($key, $fallback) === '1';
    }

    private function normalizeFxInput(mixed $value): string
    {
        $s = str_replace(',', '.', trim((string) $value));

        return $s === '' ? '0' : $s;
    }
}
