<?php

namespace App\Support;

use App\Services\Community\CommunityRichHtmlSanitizer;
use Illuminate\Support\Str;

final class CommunityRichContent
{
    public static function isLikelyHtml(string $body): bool
    {
        $t = trim($body);
        if ($t === '') {
            return false;
        }

        if (str_starts_with($t, '<')) {
            return true;
        }

        return (bool) preg_match('/<[a-z][^>\\n]*>/i', $t);
    }

    public static function render(string $body): string
    {
        if (self::isLikelyHtml($body)) {
            return app(CommunityRichHtmlSanitizer::class)->sanitize($body);
        }

        return Str::markdown($body);
    }

    /** Düz metin uzunluğu (doğrulama / özet) */
    public static function plainText(string $body): string
    {
        $plain = strip_tags($body);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = str_replace("\xc2\xa0", ' ', $plain);

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    }

    public static function plainTextLength(string $body): int
    {
        return mb_strlen(self::plainText($body));
    }

    public static function isEffectivelyEmpty(string $html): bool
    {
        $collapsed = preg_replace('/\s+/u', '', self::plainText($html)) ?? '';

        return $collapsed === '';
    }
}
