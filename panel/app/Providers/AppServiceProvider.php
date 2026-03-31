<?php

namespace App\Providers;

use App\Models\Database;
use App\Models\Domain;
use App\Policies\DatabasePolicy;
use App\Policies\DomainPolicy;
use App\Services\HostingQuotaService;
use App\Services\OutboundMailConfigurator;
use App\Services\UserHostingPackageSync;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HostingQuotaService::class);
        $this->app->singleton(UserHostingPackageSync::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(600)->by($request->ip());
        });

        // Dosya yöneticisi: okuma (listele/oku/indir) daha yüksek limit
        RateLimiter::for('files-read', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Dosya yöneticisi: yazma/silme/taşıma/yeniden adlandırma
        RateLimiter::for('files-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Upload daha kısıtlı
        RateLimiter::for('files-upload', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Deploy tetikleri daha sıkı limitlenir.
        RateLimiter::for('deploy-run', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        // Backup yazma/schedule/destination işlemleri.
        RateLimiter::for('backups-write', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Eklenti mağazası kurulum/aktivasyon/migration başlatma.
        RateLimiter::for('plugins-write', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        Gate::policy(Domain::class, DomainPolicy::class);
        Gate::policy(Database::class, DatabasePolicy::class);

        OutboundMailConfigurator::apply();

        if ($this->app->environment('production')) {
            if (config('app.debug')) {
                Log::warning('Panelsar: APP_DEBUG is enabled in production.');
            }
            if ((string) config('panelsar.engine_internal_key', '') === '') {
                Log::warning('Panelsar: ENGINE_INTERNAL_KEY is empty; engine integration will fail.');
            }
        }
    }
}
