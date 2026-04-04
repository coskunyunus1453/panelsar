<?php

namespace Database\Seeders;

use App\Models\LandingSiteSetting;
use Illuminate\Database\Seeder;

class LandingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        LandingSiteSetting::put('landing.default_locale', 'tr');
        LandingSiteSetting::put('landing.enabled_locales', json_encode(['tr', 'en']));

        LandingSiteSetting::put('landing.site_name', '');
        LandingSiteSetting::put('landing.site_tagline', '');
        LandingSiteSetting::put('landing.site_logo_path', '');
        LandingSiteSetting::put('landing.site_logo_max_height_px', '');
        LandingSiteSetting::put('landing.site_logo_max_width_px', '');
        LandingSiteSetting::put('landing.site_logo_footer_max_height_px', '');
        LandingSiteSetting::put('landing.site_logo_footer_max_width_px', '');
        LandingSiteSetting::put('landing.favicon_path', '');
        LandingSiteSetting::put('landing.contact_email', '');
        LandingSiteSetting::put('landing.social_twitter_url', '');
        LandingSiteSetting::put('landing.social_github_url', '');
        LandingSiteSetting::put('landing.social_linkedin_url', '');
        LandingSiteSetting::put('landing.analytics_ga4_id', '');
        LandingSiteSetting::put('landing.footer_extra_note', '');

        LandingSiteSetting::put('landing.active_theme', 'orange');
        LandingSiteSetting::put('landing.graphic_motif', 'grid');
        LandingSiteSetting::put('landing.theme_primary_hex', '');
        LandingSiteSetting::put('landing.hero_image_path', '');
        LandingSiteSetting::put('landing.hero_image_alt', '');
        LandingSiteSetting::put('landing.hero_image_caption', '');
        LandingSiteSetting::put('landing.page_overrides', '{}');
        LandingSiteSetting::put('landing.home_feature_cards', '[]');
    }
}
