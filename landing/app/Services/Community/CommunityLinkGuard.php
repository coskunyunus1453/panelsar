<?php

namespace App\Services\Community;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CommunityLinkGuard
{
    public function assertAllowed(string $html): void
    {
        $max = max(1, (int) config('community.max_links_per_body', 20));
        $count = preg_match_all('/<a\s/i', $html) ?: 0;
        if ($count > $max) {
            throw ValidationException::withMessages([
                'body' => "Gövdede en fazla {$max} bağlantıya izin verilir.",
            ]);
        }

        $blocked = config('community.blocked_link_hosts', []);
        if ($blocked === []) {
            return;
        }

        if (! preg_match_all('/href\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
            return;
        }

        foreach ($matches[2] as $href) {
            $href = trim((string) $href);
            if ($href === '' || Str::startsWith($href, '#') || Str::startsWith($href, 'mailto:')) {
                continue;
            }
            $host = parse_url($href, PHP_URL_HOST);
            if (! is_string($host) || $host === '') {
                continue;
            }
            $hostLower = strtolower($host);
            foreach ($blocked as $block) {
                if ($block !== '' && ($hostLower === $block || Str::endsWith($hostLower, '.'.$block))) {
                    throw ValidationException::withMessages([
                        'body' => 'Bu bağlantı alan adına izin verilmiyor.',
                    ]);
                }
            }
        }
    }
}
