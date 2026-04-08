<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CommunityCategory;
use App\Models\CommunityPost;
use App\Models\CommunitySiteMeta;
use App\Models\CommunityTag;
use App\Models\CommunityTopic;
use App\Support\Seo\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(Request $request): View
    {
        return $this->renderListing($request, null, null);
    }

    public function category(Request $request, CommunityCategory $category): View
    {
        if (! $category->is_active) {
            abort(404);
        }

        return $this->renderListing($request, $category, null);
    }

    public function tag(Request $request, CommunityTag $tag): View
    {
        return $this->renderListing($request, null, $tag);
    }

    public function topic(Request $request, CommunityTopic $topic): View
    {
        if ($topic->status !== CommunityTopic::STATUS_PUBLISHED) {
            abort(404);
        }
        if (! $topic->category || ! $topic->category->is_active) {
            abort(404);
        }
        if (! $this->viewerCanSeeTopic($request, $topic)) {
            abort(404);
        }

        if ($topic->moderation_status === CommunityTopic::MODERATION_APPROVED) {
            $this->maybeIncrementViews($request, $topic);
            $topic->refresh();
        }

        $topic->load(['category', 'author', 'tags']);

        $userId = $request->user()?->getKey();
        $posts = $topic->posts()
            ->where('is_hidden', false)
            ->where(function (Builder $q) use ($userId): void {
                $q->where('moderation_status', CommunityPost::MODERATION_APPROVED);
                if ($userId !== null) {
                    $q->orWhere('user_id', $userId);
                }
            })
            ->with('author')
            ->orderBy('created_at')
            ->get();

        $accepted = $topic->best_answer_post_id
            ? $posts->firstWhere('id', (int) $topic->best_answer_post_id)
            : null;
        if ($accepted instanceof CommunityPost && $accepted->moderation_status !== CommunityPost::MODERATION_APPROVED) {
            $accepted = null;
        }

        $schemaAnswerCount = CommunityPost::query()
            ->where('community_topic_id', $topic->getKey())
            ->where('is_hidden', false)
            ->where('moderation_status', CommunityPost::MODERATION_APPROVED)
            ->count();

        $similarTopics = $this->similarTopics($topic);

        $site = CommunitySiteMeta::singleton();

        $title = $topic->meta_title ?: ($topic->title.' — '.$site->site_title);
        $description = $topic->meta_description ?: ($topic->excerpt ?: $site->default_meta_description);
        $canonical = $topic->canonical_url ?: route('community.topic', $topic->slug, absolute: true);

        $robotsContent = $this->topicRobots($site, $topic);

        $breadcrumbs = [
            ['name' => 'Ana sayfa', 'url' => url('/')],
            ['name' => $site->site_title, 'url' => route('community.index', absolute: true)],
        ];
        if ($topic->category) {
            $breadcrumbs[] = [
                'name' => $topic->category->name,
                'url' => route('community.category', $topic->category->slug, absolute: true),
            ];
        }
        $breadcrumbs[] = ['name' => $topic->title, 'url' => $canonical];

        $brand = landing_p('brand.name');
        $nodes = [
            SchemaBuilder::breadcrumbList($breadcrumbs),
            SchemaBuilder::communityQuestion($topic, $canonical, $schemaAnswerCount, $accepted ?: null),
        ];
        if ($topic->moderation_status === CommunityTopic::MODERATION_APPROVED) {
            $nodes[] = SchemaBuilder::communityArticle($topic, $canonical, $brand);
        }
        $schemaJsonLd = SchemaBuilder::encode(SchemaBuilder::graph($nodes));

        return view('site.community.topic', [
            'site' => $site,
            'topic' => $topic,
            'posts' => $posts,
            'similarTopics' => $similarTopics,
            'seoTitle' => $title,
            'seoDescription' => $description,
            'canonicalUrl' => $canonical,
            'robotsContent' => $robotsContent,
            'schemaJsonLd' => $schemaJsonLd,
        ]);
    }

    private function renderListing(Request $request, ?CommunityCategory $category, ?CommunityTag $tag): View
    {
        $site = CommunitySiteMeta::singleton();
        $categories = CommunityCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $q = $this->baseTopicQuery($request, $category, $tag);

        $topics = $q->with(['category', 'author', 'tags'])->paginate(20)->withQueryString();

        if ($category instanceof CommunityCategory) {
            $title = $category->meta_title ?: ($category->name.' — '.$site->site_title);
            $description = $category->meta_description ?: $site->default_meta_description;
            $canonicalBase = route('community.category', $category->slug, absolute: true);
        } elseif ($tag instanceof CommunityTag) {
            $title = '#'.$tag->name.' — '.$site->site_title;
            $description = $site->default_meta_description;
            $canonicalBase = route('community.tag', $tag->slug, absolute: true);
        } else {
            $title = $site->default_meta_title ?: ($site->site_title.' — Soru & Cevap');
            $description = $site->default_meta_description;
            $canonicalBase = route('community.index', absolute: true);
        }

        ['robots' => $robotsContent, 'canonical' => $canonicalUrl] = $this->listingRobotsAndCanonical($request, $site, $canonicalBase);
        $schemaJsonLd = $this->listingSchemaJsonLd($site, $title, $description ?? '', $canonicalUrl, $category, $tag);

        return view('site.community.index', [
            'site' => $site,
            'categories' => $categories,
            'topics' => $topics,
            'activeCategory' => $category,
            'activeTag' => $tag,
            'seoTitle' => $title,
            'seoDescription' => $description,
            'canonicalUrl' => $canonicalUrl,
            'robotsContent' => $robotsContent,
            'schemaJsonLd' => $schemaJsonLd,
        ]);
    }

    private function baseTopicQuery(Request $request, ?CommunityCategory $category, ?CommunityTag $tag): Builder
    {
        $q = CommunityTopic::query()
            ->published()
            ->moderationApproved()
            ->whereHas('category', fn ($c) => $c->where('is_active', true));

        if ($category instanceof CommunityCategory) {
            $q->where('community_category_id', $category->id);
        }

        if ($tag instanceof CommunityTag) {
            $q->whereHas('tags', fn ($tq) => $tq->where('community_tags.id', $tag->id));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $q->where(function ($w) use ($term): void {
                $w->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term)
                    ->orWhereHas('tags', fn ($tq) => $tq->where('name', 'like', $term)->orWhere('slug', 'like', $term));
            });
        }

        $sort = $request->input('sort', 'latest');
        if ($sort === 'popular') {
            $q->orderByDesc('is_pinned')->orderByDesc('view_count')->orderByDesc('last_activity_at');
        } elseif ($sort === 'unanswered') {
            $q->where('is_solved', false)->orderByDesc('is_pinned')->orderByDesc('last_activity_at');
        } else {
            $q->orderByDesc('is_pinned')->orderByDesc('last_activity_at');
        }

        return $q;
    }

    private function similarTopics(CommunityTopic $topic)
    {
        $tagIds = $topic->tags->pluck('id');

        $q = CommunityTopic::query()
            ->published()
            ->moderationApproved()
            ->where('id', '!=', $topic->id)
            ->where('community_category_id', $topic->community_category_id);

        if ($tagIds->isNotEmpty()) {
            $q->whereHas('tags', fn ($tq) => $tq->whereIn('community_tags.id', $tagIds));
        }

        $list = $q->orderByDesc('last_activity_at')->limit(6)->get();

        if ($list->isEmpty() && $tagIds->isNotEmpty()) {
            $list = CommunityTopic::query()
                ->published()
                ->moderationApproved()
                ->where('id', '!=', $topic->id)
                ->where('community_category_id', $topic->community_category_id)
                ->orderByDesc('last_activity_at')
                ->limit(5)
                ->get();
        }

        return $list;
    }

    private function viewerCanSeeTopic(Request $request, CommunityTopic $topic): bool
    {
        if ($topic->moderation_status === CommunityTopic::MODERATION_REJECTED) {
            return false;
        }
        if ($topic->moderation_status === CommunityTopic::MODERATION_APPROVED) {
            return true;
        }

        $user = $request->user();
        if (! $user) {
            return false;
        }
        if ($user->is_admin) {
            return true;
        }

        return (int) $user->getKey() === (int) $topic->user_id;
    }

    private function topicRobots(CommunitySiteMeta $site, CommunityTopic $topic): string
    {
        $siteAllows = (bool) ($site->enable_indexing ?? true);
        $robots = $siteAllows ? 'index, follow' : 'noindex, nofollow';
        if ($topic->robots_override === 'noindex') {
            return 'noindex, nofollow';
        }
        if ($topic->robots_override === 'index') {
            $robots = 'index, follow';
        }
        if ($topic->moderation_status !== CommunityTopic::MODERATION_APPROVED) {
            return 'noindex, nofollow';
        }

        return $robots;
    }

    /**
     * @return array{robots: string, canonical: string}
     */
    private function listingRobotsAndCanonical(Request $request, CommunitySiteMeta $site, string $canonicalBase): array
    {
        $hasSearch = $request->filled('q');
        $sort = (string) $request->input('sort', 'latest');
        $nonDefaultSort = $sort !== 'latest';
        $page = max(1, (int) $request->input('page', 1));
        $filtering = $hasSearch || $nonDefaultSort;

        $siteAllows = (bool) ($site->enable_indexing ?? true);
        $robots = $siteAllows ? 'index, follow' : 'noindex, nofollow';
        if ($filtering) {
            $robots = 'noindex, follow';
        }

        $base = rtrim($canonicalBase, '?&');
        if ($filtering) {
            $canonical = $base;
            if ($page > 1) {
                $canonical .= '?page='.$page;
            }
        } elseif ($page > 1) {
            $canonical = $base.'?page='.$page;
        } else {
            $canonical = $base;
        }

        return ['robots' => $robots, 'canonical' => $canonical];
    }

    private function listingSchemaJsonLd(
        CommunitySiteMeta $site,
        string $pageTitle,
        string $description,
        string $canonicalUrl,
        ?CommunityCategory $activeCategory,
        ?CommunityTag $activeTag = null,
    ): string {
        $brand = landing_p('brand.name');
        $crumbs = [
            ['name' => 'Ana sayfa', 'url' => url('/')],
            ['name' => $site->site_title, 'url' => route('community.index', absolute: true)],
        ];
        if ($activeCategory instanceof CommunityCategory) {
            $crumbs[] = ['name' => $activeCategory->name, 'url' => route('community.category', $activeCategory->slug, absolute: true)];
        }
        if ($activeTag instanceof CommunityTag) {
            $crumbs[] = ['name' => '#'.$activeTag->name, 'url' => route('community.tag', $activeTag->slug, absolute: true)];
        }

        $nodes = [
            SchemaBuilder::breadcrumbList($crumbs),
            SchemaBuilder::webPageSimple($pageTitle, $canonicalUrl, $description, $brand),
        ];

        return SchemaBuilder::encode(SchemaBuilder::graph($nodes));
    }

    private function maybeIncrementViews(Request $request, CommunityTopic $topic): void
    {
        $ip = (string) $request->ip();
        $key = 'community:view:'.$topic->getKey().':'.$ip;
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, true, now()->addMinutes(45));
        DB::table('community_topics')->where('id', $topic->getKey())->increment('view_count');
    }
}
