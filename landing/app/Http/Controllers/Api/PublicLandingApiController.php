<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use App\Models\LandingTranslation;
use App\Services\LandingAppearance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class PublicLandingApiController extends Controller
{
    /**
     * SPA / headless landing için site görünümü ve dil meta verisi.
     */
    public function settings(): JsonResponse
    {
        $enabledJson = LandingSiteSetting::getValue('landing.enabled_locales', '["tr","en"]');
        $enabled = json_decode((string) $enabledJson, true) ?: ['tr', 'en'];
        $enabled = array_values(array_intersect($enabled, array_keys(config('landing.locales', []))));
        if ($enabled === []) {
            $enabled = ['tr'];
        }

        $default = LandingSiteSetting::getValue('landing.default_locale', Config::get('app.locale', 'tr')) ?? 'tr';
        if (! in_array($default, $enabled, true)) {
            $default = $enabled[0];
        }

        $localeLabels = collect(config('landing.locales', []))->only($enabled)->all();

        return response()->json([
            'app_url' => rtrim((string) Config::get('app.url', ''), '/'),
            'default_locale' => $default,
            'enabled_locales' => $enabled,
            'locales' => $localeLabels,
            'site_name' => trim((string) (LandingSiteSetting::getValue('landing.site_name', '') ?? '')),
            'site_tagline' => trim((string) (LandingSiteSetting::getValue('landing.site_tagline', '') ?? '')),
            'theme' => [
                'active' => LandingAppearance::activeTheme(),
                'class' => LandingAppearance::themeClass(),
                'graphic_motif_class' => LandingAppearance::graphicMotifClass(),
                'inline_style' => LandingAppearance::themeInlineStyle(),
            ],
            'assets' => [
                'logo_url' => LandingAppearance::siteLogoUrl(),
                'logo_header_max_height_px' => LandingAppearance::siteLogoHeaderMaxHeightPx(),
                'logo_header_max_width_px' => LandingAppearance::siteLogoHeaderMaxWidthPx(),
                'logo_footer_max_height_px' => LandingAppearance::siteLogoFooterMaxHeightPx(),
                'logo_footer_max_width_px' => LandingAppearance::siteLogoFooterMaxWidthPx(),
                'favicon_url' => LandingAppearance::faviconUrl(),
                'favicon_mime' => LandingAppearance::faviconMimeType(),
                'hero_image_url' => LandingAppearance::heroImageUrl(),
                'hero_image_alt' => LandingAppearance::heroImageAlt(),
                'hero_image_caption' => LandingAppearance::heroImageCaption(),
            ],
            'content' => [
                'feature_cards' => LandingAppearance::featureCards(),
                'neon' => LandingAppearance::isNeonTheme() ? [
                    'top' => LandingAppearance::neonTop(),
                    'stack_section' => LandingAppearance::neonStackSection(),
                    'stack_items' => LandingAppearance::neonStackItems(),
                    'grid_section' => LandingAppearance::neonGridSection(),
                    'grid_items' => LandingAppearance::neonGridItems(),
                ] : null,
            ],
            'contact' => [
                'email' => LandingAppearance::contactEmail(),
            ],
            'social' => [
                'twitter' => LandingAppearance::socialTwitterUrl(),
                'github' => LandingAppearance::socialGithubUrl(),
                'linkedin' => LandingAppearance::socialLinkedinUrl(),
            ],
            'analytics' => [
                'ga4_measurement_id' => LandingAppearance::analyticsMeasurementId(),
            ],
            'footer_extra_note' => LandingAppearance::footerExtraNote(),
        ]);
    }

    public function i18nConfig(): JsonResponse
    {
        $enabledJson = LandingSiteSetting::getValue('landing.enabled_locales', '["tr","en"]');
        $enabled = json_decode((string) $enabledJson, true) ?: ['tr', 'en'];
        $enabled = array_values(array_intersect($enabled, array_keys(config('landing.locales', []))));
        if ($enabled === []) {
            $enabled = ['tr'];
        }

        $default = LandingSiteSetting::getValue('landing.default_locale', Config::get('app.locale', 'tr')) ?? 'tr';
        if (! in_array($default, $enabled, true)) {
            $default = $enabled[0];
        }

        $localeLabels = collect(config('landing.locales', []))->only($enabled)->all();

        return response()->json([
            'default_locale' => $default,
            'enabled_locales' => $enabled,
            'locales' => $localeLabels,
        ]);
    }

    public function i18nOverrides(Request $request): JsonResponse
    {
        $codes = implode(',', array_keys(config('landing.locales', [])));
        $validated = $request->validate([
            'locale' => 'required|string|in:'.$codes,
        ]);

        $locale = $validated['locale'];

        $enabledJson = LandingSiteSetting::getValue('landing.enabled_locales', '["tr","en"]');
        $enabled = json_decode((string) $enabledJson, true) ?: ['tr', 'en'];
        $enabled = array_values(array_intersect($enabled, array_keys(config('landing.locales', []))));
        if ($enabled === []) {
            $enabled = ['tr'];
        }

        if (! in_array($locale, $enabled, true)) {
            $locale = $enabled[0];
        }

        $overrides = LandingTranslation::overridesFor($locale);

        return response()->json([
            'locale' => $locale,
            'messages' => $overrides,
        ]);
    }
}
