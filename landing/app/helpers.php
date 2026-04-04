<?php

use App\Services\LandingAppearance;
use App\Services\LandingI18n;

function landing_t(string $key, array $replace = []): string
{
    return app(LandingI18n::class)->line($key, $replace);
}

/** Ön yüz içerik düzenleyicisinden gelen metin önceliği (boşsa çeviri). */
function landing_p(string $key, array $replace = []): string
{
    return LandingAppearance::line($key, $replace);
}

/** Dil seçicisi için kısa etiket (örn. tr → TR, en → EN). */
function landing_locale_tag(string $code): string
{
    $normalized = str_replace('_', '-', $code);
    $primary = explode('-', $normalized, 2)[0] ?? $normalized;

    return strtoupper(substr($primary, 0, 2));
}
