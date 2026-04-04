<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Ortak Markdown → HTML (ham HTML / tehlikeli linkler güvenli modda).
 *
 * @see https://commonmark.thephpleague.com/security/
 */
final class SafeMarkdown
{
    public static function toHtml(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
