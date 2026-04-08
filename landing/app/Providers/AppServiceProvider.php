<?php

namespace App\Providers;

use App\View\Composers\LandingAppearanceComposer;
use App\View\Composers\NavMenuComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('license-validate', function () {
            return Limit::perMinute(120)->by(request()->ip());
        });

        RateLimiter::for('licensing-checkout', function () {
            return Limit::perMinute(15)->by(request()->ip());
        });

        RateLimiter::for('licensing-order', function () {
            return Limit::perMinute(30)->by(request()->ip());
        });

        RateLimiter::for('community-topic-create', function (Request $request) {
            $by = (string) ($request->user()?->getKey() ?? $request->ip());

            return [
                Limit::perHour(18)->by('ct-h:'.$by),
                Limit::perDay(60)->by('ct-d:'.$by),
            ];
        });

        RateLimiter::for('community-reply', function (Request $request) {
            $by = (string) ($request->user()?->getKey() ?? $request->ip());

            return [
                Limit::perMinute(10)->by('cr-m:'.$by),
                Limit::perHour(150)->by('cr-h:'.$by),
            ];
        });

        $landingViews = [
            'components.layouts.landing',
            'components.site.layout',
            'components.landing.neon-header',
            'components.landing.neon-footer',
            'components.landing.neon-drawer',
            'landing.home',
            'site.page',
            'site.pricing',
            'site.license-success',
            'site.license-cancel',
            'site.blog.index',
            'site.blog.show',
            'site.docs.index',
            'site.docs.show',
            'site.auth.login',
            'site.auth.register',
            'site.auth.forgot-password',
            'site.auth.reset-password',
            'site.community.index',
            'site.community.topic',
            'site.community.ask',
            'site.community.profile',
        ];

        View::composer($landingViews, LandingAppearanceComposer::class);
        View::composer($landingViews, NavMenuComposer::class);

        if ($this->app->environment('testing')) {
            $path = sys_get_temp_dir().'/hostvim-landing-blade';
            if (! is_dir($path)) {
                @mkdir($path, 0777, true);
            }
            config(['view.compiled' => $path]);
        }
    }
}
