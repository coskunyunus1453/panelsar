<?php

namespace App\Services\Licensing;

use App\Models\LandingSiteSetting;

class BillingProviderResolver
{
    public function resolve(?string $billingQuery, ?string $locale): string
    {
        $allowed = $this->allowedProviders();

        $forced = strtolower(trim($this->setting('billing.force_provider', (string) config('hostvim_saas.billing.force_provider', ''))));
        if (in_array($forced, $allowed, true)) {
            return $forced;
        }

        $q = strtolower(trim((string) $billingQuery));
        if (in_array($q, $allowed, true)) {
            return $q;
        }

        $default = strtolower(trim($this->setting('billing.default_provider', (string) config('hostvim_saas.billing.default_provider', 'auto'))));
        if (in_array($default, $allowed, true)) {
            return $default;
        }

        $loc = strtolower(trim((string) $locale));
        $rawTrLocales = $this->setting('billing.tr_locales', implode(',', (array) config('hostvim_saas.billing.turkish_locales', ['tr'])));
        $trLocales = array_values(array_filter(array_map(static fn (string $v): string => strtolower(trim($v)), explode(',', $rawTrLocales))));
        if ($trLocales === []) {
            $trLocales = ['tr'];
        }

        if (in_array($loc, $trLocales, true) && in_array('paytr', $allowed, true)) {
            return 'paytr';
        }

        if (in_array('stripe', $allowed, true)) {
            return 'stripe';
        }

        return $allowed[0] ?? 'stripe';
    }

    /**
     * @return array<int, string>
     */
    private function allowedProviders(): array
    {
        $allowed = [];

        if ($this->settingBool('billing.methods.paytr.enabled', true)) {
            $allowed[] = 'paytr';
        }
        if ($this->settingBool('billing.methods.stripe.enabled', true)) {
            $allowed[] = 'stripe';
        }
        if ($this->settingBool('billing.methods.bank_transfer.enabled', false)) {
            $allowed[] = 'bank_transfer';
        }

        if ($allowed === []) {
            return ['paytr', 'stripe'];
        }

        return $allowed;
    }

    private function setting(string $key, string $default): string
    {
        return trim((string) (LandingSiteSetting::getValue($key, $default) ?? $default));
    }

    private function settingBool(string $key, bool $default): bool
    {
        $fallback = $default ? '1' : '0';

        return $this->setting($key, $fallback) === '1';
    }
}
