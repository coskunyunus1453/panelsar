<?php

namespace App\Services\Community;

class CommunityBodySanitizer
{
    public function __construct(
        private CommunityRichHtmlSanitizer $richHtml,
    ) {}

    public function finalizeRichBody(string $raw): string
    {
        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw) ?? '';
        if (mb_strlen($raw) > 60000) {
            $raw = mb_substr($raw, 0, 60000);
        }

        return trim($this->richHtml->sanitize($raw));
    }

    /**
     * Özet metni: HTML veya Markdown gövdeden düz metin üretir.
     */
    public function excerpt(string $body, int $max = 280): string
    {
        $plain = strip_tags($body);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = str_replace("\xc2\xa0", ' ', $plain);
        $oneLine = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
        if (mb_strlen($oneLine) > 2000) {
            $oneLine = mb_substr($oneLine, 0, 2000);
        }

        return mb_strlen($oneLine) > $max ? mb_substr($oneLine, 0, $max - 1).'…' : $oneLine;
    }
}
