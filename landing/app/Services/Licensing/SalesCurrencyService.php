<?php

namespace App\Services\Licensing;

use App\Models\LandingSiteSetting;
use App\Models\SaasLicenseProduct;

/**
 * Satış para birimi, manuel kurlar ve ürün fiyatlarını (TRY / USD / EUR minor) ödeme sağlayıcısına göre çözümler.
 */
class SalesCurrencyService
{
    public function displayCurrency(): string
    {
        $c = strtoupper(trim($this->setting('billing.sales.display_currency', 'TRY')));

        return in_array($c, ['TRY', 'USD', 'EUR'], true) ? $c : 'TRY';
    }

    /** Stripe Checkout için: usd | eur */
    public function stripeCheckoutCurrency(): string
    {
        $c = strtolower(trim($this->setting('billing.stripe.checkout_currency', 'usd')));

        return in_array($c, ['usd', 'eur'], true) ? $c : 'usd';
    }

    public function tryPerUsd(): float
    {
        return $this->positiveRate('billing.fx.try_per_usd', 35.0);
    }

    public function tryPerEur(): float
    {
        return $this->positiveRate('billing.fx.try_per_eur', 38.0);
    }

    /**
     * @return array{display_currency: string, stripe_checkout_currency: string, try_per_usd: float, try_per_eur: float}|array<string, mixed>
     */
    public function publicConfig(): array
    {
        return [
            'display_currency' => $this->displayCurrency(),
            'stripe_checkout_currency' => strtoupper($this->stripeCheckoutCurrency()),
            'try_per_usd' => $this->tryPerUsd(),
            'try_per_eur' => $this->tryPerEur(),
        ];
    }

    /**
     * @return array{minor: int, currency: string}|null
     */
    public function resolveForProvider(SaasLicenseProduct $product, string $provider): ?array
    {
        return match ($provider) {
            'paytr' => $this->resolvePaytr($product),
            'stripe' => $this->resolveStripe($product),
            'bank_transfer' => $this->resolveBankTransfer($product),
            default => null,
        };
    }

    /**
     * @return array{minor: int, currency: string}|null
     */
    private function resolvePaytr(SaasLicenseProduct $product): ?array
    {
        $try = $this->positiveMinor($product->price_try_minor);
        if ($try !== null) {
            return ['minor' => $try, 'currency' => 'TRY'];
        }
        $usd = $this->positiveMinor($product->price_usd_minor);
        if ($usd !== null) {
            return ['minor' => $this->usdMinorToTryMinor($usd), 'currency' => 'TRY'];
        }
        $eur = $this->positiveMinor($product->price_eur_minor);
        if ($eur !== null) {
            return ['minor' => $this->eurMinorToTryMinor($eur), 'currency' => 'TRY'];
        }

        return null;
    }

    /**
     * @return array{minor: int, currency: string}|null
     */
    private function resolveStripe(SaasLicenseProduct $product): ?array
    {
        return $this->resolveForCheckoutCurrency($product, $this->stripeCheckoutCurrency());
    }

    /**
     * Stripe Checkout veya havale ekranında gösterilecek USD/EUR tutarı (minor birim).
     *
     * @param  'usd'|'eur'  $checkoutCcy
     * @return array{minor: int, currency: string}|null
     */
    private function resolveForCheckoutCurrency(SaasLicenseProduct $product, string $checkoutCcy): ?array
    {
        if ($checkoutCcy === 'eur') {
            $eur = $this->positiveMinor($product->price_eur_minor);
            if ($eur !== null) {
                return ['minor' => $eur, 'currency' => 'EUR'];
            }
            $try = $this->positiveMinor($product->price_try_minor);
            if ($try !== null) {
                return ['minor' => $this->tryMinorToEurMinor($try), 'currency' => 'EUR'];
            }
            $usd = $this->positiveMinor($product->price_usd_minor);
            if ($usd !== null) {
                $tryMid = $this->usdMinorToTryMinor($usd);

                return ['minor' => $this->tryMinorToEurMinor($tryMid), 'currency' => 'EUR'];
            }

            return null;
        }

        $usd = $this->positiveMinor($product->price_usd_minor);
        if ($usd !== null) {
            return ['minor' => $usd, 'currency' => 'USD'];
        }
        $try = $this->positiveMinor($product->price_try_minor);
        if ($try !== null) {
            return ['minor' => $this->tryMinorToUsdMinor($try), 'currency' => 'USD'];
        }
        $eur = $this->positiveMinor($product->price_eur_minor);
        if ($eur !== null) {
            $tryMid = $this->eurMinorToTryMinor($eur);

            return ['minor' => $this->tryMinorToUsdMinor($tryMid), 'currency' => 'USD'];
        }

        return null;
    }

    /**
     * @return array{minor: int, currency: string}|null
     */
    private function resolveBankTransfer(SaasLicenseProduct $product): ?array
    {
        $target = $this->displayCurrency();

        return match ($target) {
            'USD' => $this->resolveForCheckoutCurrency($product, 'usd'),
            'EUR' => $this->resolveForCheckoutCurrency($product, 'eur'),
            default => $this->resolvePaytr($product),
        };
    }

    public function tryMinorToUsdMinor(int $tryMinor): int
    {
        $rate = $this->tryPerUsd();
        $tryMajor = $tryMinor / 100.0;
        $usdMajor = $tryMajor / $rate;

        return max(1, (int) round($usdMajor * 100));
    }

    public function usdMinorToTryMinor(int $usdMinor): int
    {
        $rate = $this->tryPerUsd();
        $usdMajor = $usdMinor / 100.0;
        $tryMajor = $usdMajor * $rate;

        return max(1, (int) round($tryMajor * 100));
    }

    public function tryMinorToEurMinor(int $tryMinor): int
    {
        $rate = $this->tryPerEur();
        $tryMajor = $tryMinor / 100.0;
        $eurMajor = $tryMajor / $rate;

        return max(1, (int) round($eurMajor * 100));
    }

    public function eurMinorToTryMinor(int $eurMinor): int
    {
        $rate = $this->tryPerEur();
        $eurMajor = $eurMinor / 100.0;
        $tryMajor = $eurMajor * $rate;

        return max(1, (int) round($tryMajor * 100));
    }

    private function positiveMinor(mixed $v): ?int
    {
        if ($v === null) {
            return null;
        }
        $i = (int) $v;

        return $i > 0 ? $i : null;
    }

    private function positiveRate(string $key, float $default): float
    {
        $raw = trim((string) (LandingSiteSetting::getValue($key, (string) $default) ?? ''));
        if ($raw === '') {
            return $default;
        }
        $f = (float) str_replace(',', '.', $raw);

        return $f > 0 ? $f : $default;
    }

    private function setting(string $key, string $default): string
    {
        return trim((string) (LandingSiteSetting::getValue($key, $default) ?? $default));
    }
}
