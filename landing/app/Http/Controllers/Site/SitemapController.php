<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\CommunitySiteMeta;
use App\Models\CommunityTag;
use App\Models\CommunityTopic;
use App\Models\DocPage;
use App\Models\SitePage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

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

        if (Schema::hasTable('community_site_meta') && Schema::hasTable('community_topics')) {
            $meta = CommunitySiteMeta::query()->first();
            if ($meta && $meta->enable_indexing) {
                $entries[] = ['loc' => route('community.index', absolute: true), 'lastmod' => now()];
                $topicsQuery = CommunityTopic::query()
                    ->published()
                    ->whereHas('category', fn ($q) => $q->where('is_active', true));
                if (Schema::hasColumn('community_topics', 'moderation_status')) {
                    $topicsQuery->where('moderation_status', CommunityTopic::MODERATION_APPROVED);
                }
                foreach ($topicsQuery->orderByDesc('updated_at')->get(['slug', 'updated_at']) as $t) {
                    $entries[] = [
                        'loc' => route('community.topic', $t->slug, absolute: true),
                        'lastmod' => $t->updated_at,
                    ];
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
                        $entries[] = [
                            'loc' => route('community.tag', $tag->slug, absolute: true),
                            'lastmod' => $tag->updated_at,
                        ];
                    }
                }
            }
        }

        return response()
            ->view('site.sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
