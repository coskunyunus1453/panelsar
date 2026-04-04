<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\DocPage;
use App\Models\SitePage;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $locale = config('app.locale');

        $entries = [];

        $entries[] = ['loc' => url('/'), 'lastmod' => now()];

        foreach (BlogPost::query()->published()->forLocale($locale)->orderByDesc('updated_at')->get(['slug', 'updated_at']) as $post) {
            $entries[] = [
                'loc' => route('blog.show', $post->slug, absolute: true),
                'lastmod' => $post->updated_at,
            ];
        }

        $categoryIds = BlogPost::query()
            ->published()
            ->forLocale($locale)
            ->whereNotNull('blog_category_id')
            ->distinct()
            ->pluck('blog_category_id');

        foreach (BlogCategory::query()->forLocale($locale)->whereIn('id', $categoryIds)->get(['slug', 'updated_at']) as $cat) {
            $entries[] = [
                'loc' => route('blog.category', $cat->slug, absolute: true),
                'lastmod' => $cat->updated_at,
            ];
        }

        foreach (DocPage::query()->published()->forLocale($locale)->orderBy('id')->get(['slug', 'updated_at']) as $doc) {
            $entries[] = [
                'loc' => route('docs.show', $doc->slug, absolute: true),
                'lastmod' => $doc->updated_at,
            ];
        }

        foreach (SitePage::query()->published()->forLocale($locale)->orderBy('id')->get(['slug', 'updated_at']) as $p) {
            $loc = $p->slug === 'setup'
                ? route('site.setup', absolute: true)
                : route('site.page', $p->slug, absolute: true);
            $entries[] = ['loc' => $loc, 'lastmod' => $p->updated_at];
        }

        foreach (['blog.index', 'docs.index', 'site.pricing'] as $name) {
            $entries[] = ['loc' => route($name, absolute: true), 'lastmod' => now()];
        }

        return response()
            ->view('site.sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
