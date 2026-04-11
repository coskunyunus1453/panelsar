<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\CommunitySiteMeta;
use App\Models\CommunityTag;
use App\Models\CommunityTopic;
use App\Models\DocPage;
use App\Models\LandingSiteSetting;
use App\Models\SitePage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $locales = $this->enabledLocales();
        $default = $this->defaultLocale($locales);

        $entries = [];

        $sharedRouteRows = [
            ['name' => 'landing.home', 'params' => []],
            ['name' => 'site.setup', 'params' => []],
            ['name' => 'site.pricing', 'params' => []],
            ['name' => 'blog.index', 'params' => []],
            ['name' => 'docs.index', 'params' => []],
        ];

        foreach ($sharedRouteRows as $row) {
            foreach ($locales as $locale) {
                $loc = landing_url_with_lang(route($row['name'], $row['params'], absolute: true), $locale);
                $entries[] = [
                    'loc' => $loc,
                    'lastmod' => now(),
                    'alternates' => $this->hreflangBlock($locales, $default, fn (string $l) => landing_url_with_lang(route($row['name'], $row['params'], absolute: true), $l)),
                ];
            }
        }

        foreach (['license.success', 'license.cancel'] as $routeName) {
            foreach ($locales as $locale) {
                $loc = landing_url_with_lang(route($routeName, absolute: true), $locale);
                $entries[] = [
                    'loc' => $loc,
                    'lastmod' => now(),
                    'alternates' => $this->hreflangBlock($locales, $default, fn (string $l) => landing_url_with_lang(route($routeName, absolute: true), $l)),
                ];
            }
        }

        foreach (BlogPost::query()->published()->orderByDesc('updated_at')->get(['slug', 'updated_at', 'locale']) as $post) {
            $loc = landing_url_with_lang(route('blog.show', $post->slug, absolute: true), (string) $post->locale);
            $entries[] = ['loc' => $loc, 'lastmod' => $post->updated_at, 'alternates' => []];
        }

        foreach (BlogCategory::query()->orderBy('locale')->orderBy('slug')->get(['id', 'slug', 'locale', 'updated_at']) as $cat) {
            $hasPosts = BlogPost::query()
                ->published()
                ->where('blog_category_id', $cat->id)
                ->where('locale', $cat->locale)
                ->exists();
            if (! $hasPosts) {
                continue;
            }
            $loc = landing_url_with_lang(route('blog.category', $cat->slug, absolute: true), (string) $cat->locale);
            $entries[] = ['loc' => $loc, 'lastmod' => $cat->updated_at, 'alternates' => []];
        }

        foreach (DocPage::query()->published()->orderBy('locale')->orderBy('id')->get(['slug', 'updated_at', 'locale']) as $doc) {
            $loc = landing_url_with_lang(route('docs.show', $doc->slug, absolute: true), (string) $doc->locale);
            $entries[] = ['loc' => $loc, 'lastmod' => $doc->updated_at, 'alternates' => []];
        }

        foreach (SitePage::query()->published()->orderBy('locale')->orderBy('id')->get(['slug', 'updated_at', 'locale']) as $p) {
            $base = $p->slug === 'setup'
                ? route('site.setup', absolute: true)
                : route('site.page', $p->slug, absolute: true);
            $loc = landing_url_with_lang($base, (string) $p->locale);
            $entries[] = ['loc' => $loc, 'lastmod' => $p->updated_at, 'alternates' => []];
        }

        if (Schema::hasTable('community_site_meta') && Schema::hasTable('community_topics')) {
            $meta = CommunitySiteMeta::query()->first();
            if ($meta && $meta->enable_indexing) {
                foreach ($locales as $locale) {
                    $loc = landing_url_with_lang(route('community.index', absolute: true), $locale);
                    $entries[] = [
                        'loc' => $loc,
                        'lastmod' => now(),
                        'alternates' => $this->hreflangBlock($locales, $default, fn (string $l) => landing_url_with_lang(route('community.index', absolute: true), $l)),
                    ];
                }

                $topicsQuery = CommunityTopic::query()
                    ->published()
                    ->whereHas('category', fn ($q) => $q->where('is_active', true));
                if (Schema::hasColumn('community_topics', 'moderation_status')) {
                    $topicsQuery->where('moderation_status', CommunityTopic::MODERATION_APPROVED);
                }

                foreach ($topicsQuery->orderByDesc('updated_at')->get(['slug', 'updated_at']) as $t) {
                    foreach ($locales as $locale) {
                        $loc = landing_url_with_lang(route('community.topic', $t->slug, absolute: true), $locale);
                        $entries[] = [
                            'loc' => $loc,
                            'lastmod' => $t->updated_at,
                            'alternates' => $this->hreflangBlock($locales, $default, fn (string $l) => landing_url_with_lang(route('community.topic', $t->slug, absolute: true), $l)),
                        ];
                    }
                }

                if (Schema::hasTable('community_tags')) {
                    foreach (CommunityTag::query()
                        ->whereHas('topics', function ($q): void {
                            $q->where('status', CommunityTopic::STATUS_PUBLISHED);
                            if (Schema::hasColumn('community_topics', 'moderation_status')) {
                                $q->where('moderation_status', CommunityTopic::MODERATION_APPROVED);
                            }
                        })
                        ->orderBy('slug')
                        ->get(['slug', 'updated_at']) as $tag) {
                        foreach ($locales as $locale) {
                            $loc = landing_url_with_lang(route('community.tag', $tag->slug, absolute: true), $locale);
                            $entries[] = [
                                'loc' => $loc,
                                'lastmod' => $tag->updated_at,
                                'alternates' => $this->hreflangBlock($locales, $default, fn (string $l) => landing_url_with_lang(route('community.tag', $tag->slug, absolute: true), $l)),
                            ];
                        }
                    }
                }

                $categorySlugs = CommunityTopic::query()
                    ->published()
                    ->whereHas('category', fn ($q) => $q->where('is_active', true))
                    ->when(Schema::hasColumn('community_topics', 'moderation_status'), fn ($q) => $q->where('moderation_status', CommunityTopic::MODERATION_APPROVED))
                    ->with('category')
                    ->get()
                    ->pluck('category.slug')
                    ->filter()
                    ->unique();

                foreach ($categorySlugs as $slug) {
                    foreach ($locales as $locale) {
                        $loc = landing_url_with_lang(route('community.category', $slug, absolute: true), $locale);
                        $entries[] = [
                            'loc' => $loc,
                            'lastmod' => now(),
                            'alternates' => $this->hreflangBlock($locales, $default, fn (string $l) => landing_url_with_lang(route('community.category', $slug, absolute: true), $l)),
                        ];
                    }
                }
            }
        }

        return response()
            ->view('site.sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * @param  list<string>  $locales
     * @return list<array{hreflang: string, href: string}>
     */
    private function hreflangBlock(array $locales, string $default, callable $urlForLocale): array
    {
        $out = [];
        foreach ($locales as $l) {
            $out[] = [
                'hreflang' => str_replace('_', '-', $l),
                'href' => $urlForLocale($l),
            ];
        }
        $out[] = [
            'hreflang' => 'x-default',
            'href' => $urlForLocale($default),
        ];

        return $out;
    }

    /**
     * @return list<string>
     */
    private function enabledLocales(): array
    {
        $enabledJson = LandingSiteSetting::getValue('landing.enabled_locales', '["tr","en"]');
        $enabled = json_decode((string) $enabledJson, true) ?: ['tr', 'en'];
        $enabled = array_values(array_intersect($enabled, array_keys(config('landing.locales', []))));
        if ($enabled === []) {
            $enabled = ['tr'];
        }

        return $enabled;
    }

    /**
     * @param  list<string>  $enabled
     */
    private function defaultLocale(array $enabled): string
    {
        $default = LandingSiteSetting::getValue('landing.default_locale', config('app.locale', 'tr')) ?? 'tr';
        if (! in_array($default, $enabled, true)) {
            $default = $enabled[0];
        }

        return $default;
    }
}
