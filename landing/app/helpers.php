<?php

use App\Models\User;
use App\Services\LandingAppearance;
use App\Services\LandingI18n;
use App\Support\CommunityRichContent;

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

/** Topluluk gövdesi: HTML (Quill) veya eski Markdown için güvenli çıktı. */
function community_rich_display(?string $body): string
{
    if ($body === null || $body === '') {
        return '';
    }

    return CommunityRichContent::render($body);
}

/** Güvenli HTTPS avatar; yoksa Gravatar (d=mp). */
function community_user_avatar_url(?User $user, int $size = 80): string
{
    $size = max(16, min(512, $size));
    if ($user === null) {
        return 'https://www.gravatar.com/avatar/00000000000000000000000000000000?s='.$size.'&d=mp';
    }
    $url = $user->avatar_url ?? '';
    if (is_string($url) && str_starts_with($url, 'https://') && strlen($url) <= 512 && ! preg_match('/[\s<>"\'\\\\]/', $url)) {
        return $url;
    }
    $hash = md5(strtolower(trim((string) $user->email)));

    return 'https://www.gravatar.com/avatar/'.$hash.'?s='.$size.'&d=mp';
}

/**
 * Mevcut istek path’i için belirtilen dilde tam URL (?lang=… birleştirilir).
 */
function landing_localized_url(string $locale): string
{
    $request = request();
    if ($request === null) {
        return url('/').'?lang='.rawurlencode($locale);
    }

    $qs = $request->query();
    $qs['lang'] = $locale;
    ksort($qs);

    return $request->url().($qs === [] ? '' : '?'.http_build_query($qs));
}

/**
 * Mutlak URL’ye (veya path + mevcut site köküne) lang parametresi ekler — canonical ve sitemap için.
 */
function landing_url_with_lang(string $absoluteUrl, string $locale): string
{
    $parts = parse_url($absoluteUrl);
    if ($parts === false) {
        return $absoluteUrl;
    }

    $scheme = $parts['scheme'] ?? (request()?->getScheme() ?? 'https');
    $host = $parts['host'] ?? (request()?->getHost() ?? '');
    $port = isset($parts['port']) ? ':'.$parts['port'] : '';
    $path = $parts['path'] ?? '/';

    $query = [];
    if (! empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['lang'] = $locale;
    ksort($query);

    return $scheme.'://'.$host.$port.$path.'?'.http_build_query($query);
}

/** Open Graph locale biçimi (tr_TR, en_US). */
function landing_og_locale_tag(string $code): string
{
    $primary = strtolower(substr(str_replace('_', '-', $code), 0, 2));

    return match ($primary) {
        'tr' => 'tr_TR',
        'en' => 'en_US',
        default => str_replace('-', '_', $code),
    };
}

/** Ana sayfa mutlak adresi, aktif içerik dili ile. */
function landing_home_localized_url(?string $locale = null): string
{
    $locale ??= app()->getLocale();

    return landing_url_with_lang(url('/'), $locale);
}
