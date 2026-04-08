<?php

namespace App\Support;

final class SafeInternalRedirect
{
    /**
     * Normalize a same-origin path + optional query for safe redirects (open-redirect koruması).
     */
    public static function path(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $path = urldecode(trim($value));

        if (strlen($path) > 2048 || str_contains($path, "\r") || str_contains($path, "\n")) {
            return null;
        }

        if (str_contains($path, '://') || str_contains($path, '//')) {
            return null;
        }

        if (! str_starts_with($path, '/')) {
            return null;
        }

        return $path;
    }
}
