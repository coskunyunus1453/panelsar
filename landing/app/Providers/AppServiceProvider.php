<?php

namespace App\Providers;

use App\View\Composers\LandingAppearanceComposer;
use App\View\Composers\NavMenuComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $landingViews = [
            'components.layouts.landing',
            'components.site.layout',
            'components.landing.neon-header',
            'components.landing.neon-footer',
            'components.landing.neon-drawer',
            'landing.home',
            'site.page',
            'site.pricing',
            'site.blog.index',
            'site.blog.show',
            'site.docs.index',
            'site.docs.show',
        ];

        View::composer($landingViews, LandingAppearanceComposer::class);
        View::composer($landingViews, NavMenuComposer::class);

        if ($this->app->environment('testing')) {
            $path = sys_get_temp_dir().'/panelsar-landing-blade';
            if (! is_dir($path)) {
                @mkdir($path, 0777, true);
            }
            config(['view.compiled' => $path]);
        }
    }
}
