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
