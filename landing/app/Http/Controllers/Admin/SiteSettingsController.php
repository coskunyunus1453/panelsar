<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use App\Services\LandingAppearance;
use App\Services\LandingI18n;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.site-settings.edit', [
            'siteName' => trim((string) (LandingSiteSetting::getValue('landing.site_name', '') ?? '')),
            'siteTagline' => trim((string) (LandingSiteSetting::getValue('landing.site_tagline', '') ?? '')),
            'logoUrl' => LandingAppearance::siteLogoUrl(),
            'faviconUrl' => LandingAppearance::faviconUrl(),
            'contactEmail' => trim((string) (LandingSiteSetting::getValue('landing.contact_email', '') ?? '')),
            'socialTwitter' => trim((string) (LandingSiteSetting::getValue('landing.social_twitter_url', '') ?? '')),
            'socialGithub' => trim((string) (LandingSiteSetting::getValue('landing.social_github_url', '') ?? '')),
            'socialLinkedin' => trim((string) (LandingSiteSetting::getValue('landing.social_linkedin_url', '') ?? '')),
            'analyticsGa4' => trim((string) (LandingSiteSetting::getValue('landing.analytics_ga4_id', '') ?? '')),
            'footerExtraNote' => trim((string) (LandingSiteSetting::getValue('landing.footer_extra_note', '') ?? '')),
            'logoMaxHeightPx' => (string) (LandingSiteSetting::getValue('landing.site_logo_max_height_px', '') ?? ''),
            'logoMaxWidthPx' => (string) (LandingSiteSetting::getValue('landing.site_logo_max_width_px', '') ?? ''),
            'logoFooterMaxHeightPx' => (string) (LandingSiteSetting::getValue('landing.site_logo_footer_max_height_px', '') ?? ''),
            'logoFooterMaxWidthPx' => (string) (LandingSiteSetting::getValue('landing.site_logo_footer_max_width_px', '') ?? ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        foreach ([
            'site_logo_max_height_px',
            'site_logo_max_width_px',
            'site_logo_footer_max_height_px',
            'site_logo_footer_max_width_px',
        ] as $logoDimKey) {
            if ($request->input($logoDimKey) === '') {
                $request->merge([$logoDimKey => null]);
            }
        }

        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:200'],
            'site_logo' => ['nullable', 'file', 'max:4096', 'mimes:png,jpg,jpeg,webp,svg'],
            'favicon' => ['nullable', 'file', 'max:1024', 'mimes:png,jpg,jpeg,webp,ico,svg'],
            'remove_site_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'contact_email' => ['nullable', 'string', 'max:255', 'email'],
            'social_twitter_url' => ['nullable', 'string', 'max:500'],
            'social_github_url' => ['nullable', 'string', 'max:500'],
            'social_linkedin_url' => ['nullable', 'string', 'max:500'],
            'analytics_ga4_id' => ['nullable', 'string', 'max:24'],
            'footer_extra_note' => ['nullable', 'string', 'max:500'],
            'site_logo_max_height_px' => ['nullable', 'integer', 'min:20', 'max:200'],
            'site_logo_max_width_px' => ['nullable', 'integer', 'min:0', 'max:600'],
            'site_logo_footer_max_height_px' => ['nullable', 'integer', 'min:16', 'max:120'],
            'site_logo_footer_max_width_px' => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);

        $ga = trim((string) ($validated['analytics_ga4_id'] ?? ''));
        if ($ga !== '' && ! preg_match('/^G-[A-Z0-9]+$/', $ga)) {
            throw ValidationException::withMessages([
                'analytics_ga4_id' => 'Geçerli bir GA4 ölçüm kodu girin (ör. G-XXXXXXXXXX).',
            ]);
        }

        foreach (['social_twitter_url', 'social_github_url', 'social_linkedin_url'] as $socialKey) {
            $su = trim((string) ($validated[$socialKey] ?? ''));
            if ($su !== '' && filter_var($su, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages([
                    $socialKey => 'Geçerli bir tam adres (https://…) girin veya alanı boş bırakın.',
                ]);
            }
        }

        LandingSiteSetting::put('landing.site_name', trim((string) ($validated['site_name'] ?? '')));
        LandingSiteSetting::put('landing.site_tagline', trim((string) ($validated['site_tagline'] ?? '')));
        LandingSiteSetting::put('landing.contact_email', trim((string) ($validated['contact_email'] ?? '')));
        LandingSiteSetting::put('landing.social_twitter_url', trim((string) ($validated['social_twitter_url'] ?? '')));
        LandingSiteSetting::put('landing.social_github_url', trim((string) ($validated['social_github_url'] ?? '')));
        LandingSiteSetting::put('landing.social_linkedin_url', trim((string) ($validated['social_linkedin_url'] ?? '')));
        LandingSiteSetting::put('landing.analytics_ga4_id', trim((string) ($validated['analytics_ga4_id'] ?? '')));
        LandingSiteSetting::put('landing.footer_extra_note', trim((string) ($validated['footer_extra_note'] ?? '')));

        $logoH = $validated['site_logo_max_height_px'] ?? null;
        LandingSiteSetting::put('landing.site_logo_max_height_px', $logoH !== null ? (string) $logoH : '');
        $logoW = $validated['site_logo_max_width_px'] ?? null;
        LandingSiteSetting::put('landing.site_logo_max_width_px', $logoW !== null && (int) $logoW > 0 ? (string) (int) $logoW : '');
        $logoFh = $validated['site_logo_footer_max_height_px'] ?? null;
        LandingSiteSetting::put('landing.site_logo_footer_max_height_px', $logoFh !== null ? (string) $logoFh : '');
        $logoFw = $validated['site_logo_footer_max_width_px'] ?? null;
        LandingSiteSetting::put('landing.site_logo_footer_max_width_px', $logoFw !== null && (int) $logoFw > 0 ? (string) (int) $logoFw : '');

        if ($request->boolean('remove_site_logo')) {
            $old = LandingSiteSetting::getValue('landing.site_logo_path', '');
            if (is_string($old) && $old !== '') {
                LandingAppearance::deleteLandingStoredPath($old);
            }
            LandingSiteSetting::put('landing.site_logo_path', '');
        } elseif ($request->hasFile('site_logo')) {
            $file = $request->file('site_logo');
            if ($file !== null && $file->isValid()) {
                $old = LandingSiteSetting::getValue('landing.site_logo_path', '');
                if (is_string($old) && $old !== '') {
                    LandingAppearance::deleteLandingStoredPath($old);
                }
                $ext = $file->getClientOriginalExtension() ?: 'png';
                $path = $file->storeAs('landing', 'logo-'.time().'.'.$ext, 'landing_assets');
                LandingSiteSetting::put('landing.site_logo_path', $path);
            }
        }

        if ($request->boolean('remove_favicon')) {
            $old = LandingSiteSetting::getValue('landing.favicon_path', '');
            if (is_string($old) && $old !== '') {
                LandingAppearance::deleteLandingStoredPath($old);
            }
            LandingSiteSetting::put('landing.favicon_path', '');
        } elseif ($request->hasFile('favicon')) {
            $file = $request->file('favicon');
            if ($file !== null && $file->isValid()) {
                $old = LandingSiteSetting::getValue('landing.favicon_path', '');
                if (is_string($old) && $old !== '') {
                    LandingAppearance::deleteLandingStoredPath($old);
                }
                $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
                $ext = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'ico', 'svg'], true) ? $ext : 'png';
                $path = $file->storeAs('landing', 'favicon-'.time().'.'.$ext, 'landing_assets');
                LandingSiteSetting::put('landing.favicon_path', $path);
            }
        }

        LandingI18n::clearRuntimeCache();

        return redirect()->route('admin.site-settings.edit')->with('status', 'Site ayarları kaydedildi.');
    }
}
