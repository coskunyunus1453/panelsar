<?php

namespace App\View\Composers;

use App\Services\LandingAppearance;
use Illuminate\View\View;

class LandingAppearanceComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'landingThemeClass' => LandingAppearance::themeClass(),
            'landingThemeInlineStyle' => LandingAppearance::themeInlineStyle(),
            'landingGraphicMotifClass' => LandingAppearance::graphicMotifClass(),
        ]);

        if ($view->name() === 'landing.home') {
            $view->with([
                'landingFeatureCards' => LandingAppearance::featureCards(),
                'landingHeroImageUrl' => LandingAppearance::heroImageUrl(),
                'landingHeroImageAlt' => LandingAppearance::heroImageAlt(),
                'landingHeroImageCaption' => LandingAppearance::heroImageCaption(),
                'landingNeonTop' => LandingAppearance::neonTop(),
                'landingNeonStackSection' => LandingAppearance::neonStackSection(),
                'landingNeonStackItems' => LandingAppearance::neonStackItems(),
                'landingNeonGridSection' => LandingAppearance::neonGridSection(),
                'landingNeonGridItems' => LandingAppearance::neonGridItems(),
            ]);
        }
    }
}
