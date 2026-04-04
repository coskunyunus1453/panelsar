<?php

namespace App\Support\Seo;

final class SeoUrls
{
    public static function absolute(?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null || trim($pathOrUrl) === '') {
            return null;
        }

        $v = trim($pathOrUrl);
        if (preg_match('#^https?://#i', $v)) {
            return $v;
        }

        if (str_starts_with($v, '/')) {
            return url($v);
        }

        return url('/'.$v);
    }
}
