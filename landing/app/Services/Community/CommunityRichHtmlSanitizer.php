<?php

namespace App\Services\Community;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Quill 2 çıktısına uygun dar izin listesi — script/style/olay öznitelikleri yok.
 */
final class CommunityRichHtmlSanitizer
{
    private HtmlSanitizerInterface $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->withMaxInputLength(65_000)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks(false)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('strike')
            ->allowElement('del')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('blockquote')
            ->allowElement('ol')
            ->allowElement('ul')
            ->allowElement('li')
            ->allowElement('pre', ['spellcheck'])
            ->allowElement('code')
            ->allowElement('a', ['href', 'title', 'rel', 'target']);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        $out = $this->sanitizer->sanitize($html);

        return trim($out);
    }
}
