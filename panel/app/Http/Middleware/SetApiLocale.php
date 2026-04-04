<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    /**
     * API isteklerinde seçilen dile göre Laravel locale'unu ayarlar.
     *
     * Öncelik:
     * - `X-Locale` header
     * - `locale` request body/query
     * - auth'lı kullanıcı locale'u
     * - fallback: `en`
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sadece API path'lerinde çalıştır.
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $supported = ['en', 'tr', 'de', 'fr', 'es', 'pt', 'zh', 'ja', 'ar', 'ru'];

        $locale = null;

        $headerLocale = $request->header('X-Locale');
        if (is_string($headerLocale) && trim($headerLocale) !== '') {
            $locale = $headerLocale;
        }

        if (! $locale) {
            $locale = $request->input('locale');
        }

        if (! $locale && $request->user() && is_string($request->user()->locale)) {
            $locale = $request->user()->locale;
        }

        $locale = strtolower((string) $locale);
        $locale = explode('-', $locale)[0] ?: 'en';

        if (! in_array($locale, $supported, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
