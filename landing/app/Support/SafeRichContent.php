<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Zengin metin (Quill HTML) ve eski Markdown içeriğini güvenli şekilde ön yüzde göstermek;
 * kayıtta HTML'i Symfony HtmlSanitizer ile temizlemek.
 */
final class SafeRichContent
{
    private static ?HtmlSanitizer $htmlSanitizer = null;

    public static function toHtml(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        if (self::isLikelyHtml($trimmed)) {
            return self::sanitizer()->sanitize($content);
        }

        return SafeMarkdown::toHtml($content);
    }

    /**
     * Formdan gelen içeriği veritabanına yazmadan önce (HTML ise) temizler; Markdown'ı olduğu gibi bırakır.
     */
    public static function sanitizeStored(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        if (self::isLikelyHtml($trimmed)) {
            return self::sanitizer()->sanitize($content);
        }

        return $content;
    }

    public static function isEffectivelyEmpty(string $html): bool
    {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = str_replace("\xc2\xa0", ' ', $plain);
        $plain = preg_replace('/\s+/u', '', $plain) ?? '';

        return $plain === '';
    }

    private static function isLikelyHtml(string $trimmed): bool
    {
        return str_starts_with($trimmed, '<');
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$htmlSanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->withMaxInputLength(-1)
        );
    }
}
