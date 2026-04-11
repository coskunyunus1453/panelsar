<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\DocPage;
use App\Support\Seo\SchemaBuilder;
use Illuminate\View\View;

class DocController extends Controller
{
    public function index(): View
    {
        $locale = app()->getLocale();

        $roots = DocPage::query()
            ->published()
            ->forLocale($locale)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->published()->forLocale($locale)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $schema = SchemaBuilder::graph([
            SchemaBuilder::breadcrumbList([
                ['name' => landing_p('brand.name'), 'url' => landing_home_localized_url($locale)],
                ['name' => landing_t('docs.breadcrumb'), 'url' => landing_url_with_lang(route('docs.index', absolute: true), $locale)],
            ]),
        ]);

        return view('site.docs.index', [
            'roots' => $roots,
            'seoSchema' => SchemaBuilder::encode($schema),
            'seoCanonical' => landing_url_with_lang(route('docs.index', absolute: true), $locale),
            'seoDescription' => landing_t('docs.index_meta_description'),
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $page = DocPage::query()
            ->published()
            ->forLocale($locale)
            ->where('slug', $slug)
            ->with('parent')
            ->firstOrFail();

        $canonical = $page->seoCanonicalAbsoluteUrl();
        $brand = landing_p('brand.name');

        $breadcrumbs = [
            ['name' => $brand, 'url' => landing_home_localized_url($locale)],
            ['name' => landing_t('docs.breadcrumb'), 'url' => landing_url_with_lang(route('docs.index', absolute: true), $locale)],
        ];
        if ($page->parent) {
            $breadcrumbs[] = [
                'name' => $page->parent->title,
                'url' => landing_url_with_lang(route('docs.show', $page->parent->slug, absolute: true), $locale),
            ];
        }
        $breadcrumbs[] = ['name' => $page->title, 'url' => $canonical];

        $schema = SchemaBuilder::graph([
            SchemaBuilder::techArticleDoc($page, $canonical, $brand),
            SchemaBuilder::breadcrumbList($breadcrumbs),
        ]);

        return view('site.docs.show', [
            'page' => $page,
            'seoCanonical' => $canonical,
            'seoDescription' => $page->effectiveMetaDescription(),
            'seoSchema' => SchemaBuilder::encode($schema),
        ]);
    }
}
