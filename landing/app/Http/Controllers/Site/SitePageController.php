<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SitePage;
use App\Support\Seo\SchemaBuilder;
use Illuminate\View\View;

class SitePageController extends Controller
{
    public function setup(): View
    {
        $page = SitePage::query()
            ->published()
            ->forLocale(app()->getLocale())
            ->where('slug', 'setup')
            ->firstOrFail();

        return $this->renderPage($page);
    }

    public function show(string $slug): View
    {
        $page = SitePage::query()
            ->published()
            ->forLocale(app()->getLocale())
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->renderPage($page);
    }

    private function renderPage(SitePage $page): View
    {
        $brand = landing_p('brand.name');
        $locale = (string) $page->locale;
        $canonical = $page->seoCanonicalAbsoluteUrl();
        $ogImage = $page->ogImageAbsolute();

        $schema = SchemaBuilder::graph([
            SchemaBuilder::webPageSimple(
                $page->effectiveMetaTitle(),
                $canonical,
                $page->effectiveMetaDescription(),
                $brand,
                $ogImage
            ),
            SchemaBuilder::breadcrumbList([
                ['name' => $brand, 'url' => landing_home_localized_url($locale)],
                ['name' => $page->title, 'url' => $canonical],
            ]),
        ]);

        return view('site.page', [
            'page' => $page,
            'seoCanonical' => $canonical,
            'seoDescription' => $page->effectiveMetaDescription(),
            'seoOgImage' => $ogImage,
            'seoRobots' => $page->robots,
            'seoSchema' => SchemaBuilder::encode($schema),
        ]);
    }
}
