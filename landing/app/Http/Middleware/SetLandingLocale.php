<?php

namespace App\Http\Middleware;

use App\Models\LandingSiteSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLandingLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabledJson = LandingSiteSetting::getValue('landing.enabled_locales', '["tr","en"]');
        $enabled = json_decode($enabledJson, true) ?: ['tr', 'en'];
        $enabled = array_values(array_intersect($enabled, array_keys(config('landing.locales', []))));
        if ($enabled === []) {
            $enabled = ['tr'];
        }

        $default = LandingSiteSetting::getValue('landing.default_locale', config('app.locale', 'tr')) ?? 'tr';
        if (! in_array($default, $enabled, true)) {
            $default = $enabled[0];
        }

        $locale = $request->query('lang');
        if ($locale !== null && ! in_array($locale, $enabled, true)) {
            $locale = $default;
        }
        if ($locale === null) {
            $locale = session('landing_locale', $default);
        }
        if (! in_array($locale, $enabled, true)) {
            $locale = $default;
        }

        App::setLocale($locale);
        session(['landing_locale' => $locale]);

        $localeLabels = collect(config('landing.locales', []))->only($enabled)->all();

        View::share('landingLocale', $locale);
        View::share('landingEnabledLocales', $enabled);
        View::share('landingLocaleLabels', $localeLabels);

        return $next($request);
    }
}
