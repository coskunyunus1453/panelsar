<?php

namespace App\Services\Admin;

use App\Models\BlogPost;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\DocPage;
use App\Models\NavMenuItem;
use App\Models\Plan;
use App\Models\SaasCustomer;
use App\Models\SaasLicense;
use App\Models\SitePage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $hasCommunity = Schema::hasTable('community_topics');
        $hasSaas = Schema::hasTable('saas_customers');

        $kpis = [
            'blog_published' => Schema::hasTable('blog_posts')
                ? BlogPost::query()->published()->count()
                : 0,
            'blog_drafts' => Schema::hasTable('blog_posts')
                ? BlogPost::query()->where('is_published', false)->count()
                : 0,
            'docs_published' => Schema::hasTable('doc_pages')
                ? DocPage::query()->published()->count()
                : 0,
            'site_pages' => Schema::hasTable('site_pages')
                ? SitePage::query()->published()->count()
                : 0,
            'plans' => Schema::hasTable('plans') ? Plan::query()->count() : 0,
            'nav_items' => Schema::hasTable('nav_menu_items') ? NavMenuItem::query()->count() : 0,
            'community_topics' => $hasCommunity
                ? CommunityTopic::query()->where('status', CommunityTopic::STATUS_PUBLISHED)->count()
                : 0,
            'community_posts' => $hasCommunity && Schema::hasTable('community_posts')
                ? CommunityPost::query()->count()
                : 0,
            'community_members' => $hasCommunity && Schema::hasTable('users')
                ? User::query()
                    ->where('is_admin', false)
                    ->where(function ($q): void {
                        $q->whereHas('communityTopics')->orWhereHas('communityPosts');
                    })
                    ->count()
                : 0,
            'community_pending_topics' => 0,
            'community_pending_posts' => 0,
            'community_views_sum' => 0,
            'saas_customers' => 0,
            'saas_licenses_active' => 0,
        ];

        if ($hasCommunity && Schema::hasColumn('community_topics', 'moderation_status')) {
            $kpis['community_pending_topics'] = CommunityTopic::query()
                ->where('status', CommunityTopic::STATUS_PUBLISHED)
                ->where('moderation_status', CommunityTopic::MODERATION_PENDING)
                ->count();
        }
        if ($hasCommunity && Schema::hasTable('community_posts') && Schema::hasColumn('community_posts', 'moderation_status')) {
            $kpis['community_pending_posts'] = CommunityPost::query()
                ->where('moderation_status', CommunityPost::MODERATION_PENDING)
                ->where('is_hidden', false)
                ->count();
        }
        if ($hasCommunity && Schema::hasColumn('community_topics', 'view_count')) {
            $kpis['community_views_sum'] = (int) CommunityTopic::query()->sum('view_count');
        }

        if ($hasSaas) {
            $kpis['saas_customers'] = SaasCustomer::query()->count();
            $kpis['saas_licenses_active'] = Schema::hasTable('saas_licenses')
                ? SaasLicense::query()->where('status', 'active')->count()
                : 0;
        }

        return [
            'kpis' => $kpis,
            'has_community' => $hasCommunity,
            'has_saas' => $hasSaas,
            'community_series' => $hasCommunity ? $this->dailySeries(CommunityTopic::class, 14) : [],
            'blog_series' => Schema::hasTable('blog_posts')
                ? $this->dailySeries(BlogPost::class, 14)
                : [],
            'recent_topics' => $hasCommunity
                ? CommunityTopic::query()
                    ->with('category')
                    ->orderByDesc('last_activity_at')
                    ->limit(6)
                    ->get()
                : collect(),
            'recent_blog' => Schema::hasTable('blog_posts')
                ? BlogPost::query()
                    ->orderByDesc('updated_at')
                    ->limit(6)
                    ->get()
                : collect(),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<array{label: string, count: int, height_px: int}>
     */
    private function dailySeries(string $modelClass, int $days, string $dateColumn = 'created_at'): array
    {
        $start = Carbon::now()->startOfDay()->subDays($days - 1);
        $counts = [];
        for ($i = 0; $i < $days; $i++) {
            $d = (clone $start)->addDays($i);
            $key = $d->format('Y-m-d');
            $counts[$key] = $modelClass::query()->whereDate($dateColumn, $d)->count();
        }
        $max = max($counts) ?: 1;
        $maxBarPx = 112;
        $out = [];
        foreach ($counts as $date => $count) {
            $d = Carbon::parse($date);
            $out[] = [
                'label' => $d->format('d.m'),
                'count' => $count,
                'height_px' => max(4, (int) round(($count / $max) * $maxBarPx)),
            ];
        }

        return $out;
    }
}
