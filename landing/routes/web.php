<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\SaasCustomerController;
use App\Http\Controllers\Admin\SaasDashboardController;
use App\Http\Controllers\Admin\SaasLicenseController;
use App\Http\Controllers\Admin\SaasLicenseProductController;
use App\Http\Controllers\Admin\SaasProductModuleController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocPageController;
use App\Http\Controllers\Admin\LandingTranslationController;
use App\Http\Controllers\Admin\LocaleSettingsController;
use App\Http\Controllers\Admin\NavMenuItemController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PublicHomeContentController;
use App\Http\Controllers\Admin\SitePageController as AdminSitePageController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\ThemeSettingsController;
use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\DocController;
use App\Http\Controllers\Site\PricingController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\SitePageController;
use App\Models\BlogPost;
use Illuminate\Support\Facades\Route;

Route::permanentRedirect('/kurulum', '/setup');
Route::permanentRedirect('/fiyatlandirma', '/pricing');

Route::get('/blog/kategori/{slug}', function (string $slug) {
    $map = [
        'hosting-ve-gecis' => 'hosting-migration',
        'guvenlik' => 'security',
        'olceklendirme' => 'scaling',
    ];
    $target = $map[$slug] ?? $slug;

    return redirect()->route('blog.category', ['blog_category' => $target], 301);
})->where('slug', '[a-z0-9-]+');

Route::permanentRedirect('/blog/shared-hostingten-kendi-panelime', '/blog/from-shared-hosting');
Route::permanentRedirect('/blog/panel-guvenliginde-temel-hatalar', '/blog/panel-security-basics');
Route::permanentRedirect('/blog/tek-sunucudan-coklu-clustera', '/blog/single-server-to-cluster');

Route::permanentRedirect('/docs/baslangic', '/docs/getting-started');
Route::permanentRedirect('/docs/sunucu-kurulumu', '/docs/server-setup');
Route::permanentRedirect('/docs/hostvim-mimarisi', '/docs/architecture');

Route::get('/', function () {
    $latestPosts = BlogPost::query()
        ->published()
        ->forLocale(app()->getLocale())
        ->orderByDesc('published_at')
        ->limit(3)
        ->get();

    return view('landing.home', [
        'latestPosts' => $latestPosts,
    ]);
})->name('landing.home');

Route::get('/setup', [SitePageController::class, 'setup'])->name('site.setup');
Route::get('/pricing', [PricingController::class, 'index'])->name('site.pricing');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/category/{blog_category:slug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', function () {
    $sitemap = route('sitemap', absolute: true);

    return response(
        "User-agent: *\nAllow: /\nSitemap: {$sitemap}\n",
        200,
        ['Content-Type' => 'text/plain; charset=UTF-8']
    );
})->name('robots');

Route::get('/docs', [DocController::class, 'index'])->name('docs.index');
Route::get('/docs/{slug}', [DocController::class, 'show'])->name('docs.show');

Route::get('/p/{slug}', [SitePageController::class, 'show'])->name('site.page');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::middleware('auth')->group(function (): void {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::middleware('admin')->group(function (): void {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('site-settings', [SiteSettingsController::class, 'edit'])->name('site-settings.edit');
            Route::put('site-settings', [SiteSettingsController::class, 'update'])->name('site-settings.update');

            Route::get('locale-settings', [LocaleSettingsController::class, 'edit'])->name('locale-settings.edit');
            Route::put('locale-settings', [LocaleSettingsController::class, 'update'])->name('locale-settings.update');
            Route::get('translations', [LandingTranslationController::class, 'index'])->name('translations.index');
            Route::get('translations/edit', [LandingTranslationController::class, 'edit'])->name('translations.edit');
            Route::put('translations', [LandingTranslationController::class, 'update'])->name('translations.update');

            Route::get('theme-settings', [ThemeSettingsController::class, 'edit'])->name('theme-settings.edit');
            Route::put('theme-settings', [ThemeSettingsController::class, 'update'])->name('theme-settings.update');
            Route::get('public-home-content', [PublicHomeContentController::class, 'edit'])->name('public-home-content.edit');
            Route::put('public-home-content', [PublicHomeContentController::class, 'update'])->name('public-home-content.update');

            Route::resource('site-pages', AdminSitePageController::class)->except(['show']);
            Route::resource('blog-categories', BlogCategoryController::class)->except(['show']);
            Route::resource('blog-posts', BlogPostController::class)->except(['show']);
            Route::resource('doc-pages', DocPageController::class)->except(['show']);
            Route::resource('plans', PlanController::class)->except(['show']);

            Route::get('saas', SaasDashboardController::class)->name('saas.dashboard');
            Route::resource('saas/customers', SaasCustomerController::class)->except(['show']);
            Route::resource('saas/products', SaasLicenseProductController::class)->except(['show'])
                ->parameters(['products' => 'saas_license_product']);
            Route::resource('saas/modules', SaasProductModuleController::class)->except(['show'])
                ->parameters(['modules' => 'saas_product_module']);
            Route::resource('saas/licenses', SaasLicenseController::class)->except(['show'])
                ->parameters(['licenses' => 'saas_license']);
            Route::post('saas/licenses/{saas_license}/regenerate', [SaasLicenseController::class, 'regenerate'])
                ->name('saas.licenses.regenerate');

            Route::get('nav-menu', [NavMenuItemController::class, 'index'])->name('nav-menu.index');
            Route::get('nav-menu/create', [NavMenuItemController::class, 'create'])->name('nav-menu.create');
            Route::post('nav-menu', [NavMenuItemController::class, 'store'])->name('nav-menu.store');
            Route::get('nav-menu/{item}/edit', [NavMenuItemController::class, 'edit'])->name('nav-menu.edit');
            Route::put('nav-menu/{item}', [NavMenuItemController::class, 'update'])->name('nav-menu.update');
            Route::delete('nav-menu/{item}', [NavMenuItemController::class, 'destroy'])->name('nav-menu.destroy');
            Route::post('nav-menu/reorder', [NavMenuItemController::class, 'reorder'])->name('nav-menu.reorder');
        });
    });
});
