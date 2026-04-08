<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CommunityCategory;
use App\Models\CommunityPost;
use App\Models\CommunitySiteMeta;
use App\Models\CommunityTag;
use App\Models\CommunityTopic;
use App\Services\Community\CommunityBodySanitizer;
use App\Services\Community\CommunityLinkGuard;
use App\Services\Community\CommunitySlugService;
use App\Support\CommunityRichContent;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommunityParticipationController extends Controller
{
    public function __construct(
        private CommunityBodySanitizer $sanitizer,
        private CommunitySlugService $slugs,
        private CommunityLinkGuard $linkGuard,
    ) {}

    public function ask(Request $request): View
    {
        $categories = CommunityCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $preselectedCategory = null;
        if ($request->filled('kategori')) {
            $slug = Str::limit($request->string('kategori')->trim()->toString(), 191);
            $preselectedCategory = CommunityCategory::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
        }

        $site = CommunitySiteMeta::singleton();
        $seoTitle = 'Yeni soru — '.$site->site_title;
        $seoDescription = 'Topluluğa soru sorun; arama motorları için isteğe bağlı başlık ve açıklama ayarlayabilirsiniz.';

        return view('site.community.ask', [
            'categories' => $categories,
            'preselectedCategory' => $preselectedCategory,
            'site' => $site,
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'canonicalUrl' => route('community.ask', absolute: true),
            'robotsContent' => 'noindex, follow',
        ]);
    }

    public function storeTopic(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hv_company' => ['nullable', 'string', 'max:0'],
            'community_category_id' => 'required|exists:community_categories,id',
            'title' => 'required|string|min:3|max:200',
            'tags' => ['nullable', 'string', 'max:220'],
            'body' => ['required', 'string', 'max:60000', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    $fail('Geçersiz içerik.');

                    return;
                }
                if (CommunityRichContent::isEffectivelyEmpty($value)) {
                    $fail('İçerik boş olamaz.');

                    return;
                }
                if (CommunityRichContent::plainTextLength($value) < 10) {
                    $fail('Metin olarak en az 10 karakter girin.');
                }
            }],
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:200',
        ]);

        $category = CommunityCategory::query()->where('id', $data['community_category_id'])->where('is_active', true)->first();
        if (! $category) {
            return back()->withErrors(['community_category_id' => 'Geçersiz kategori.'])->withInput();
        }

        $body = $this->sanitizer->finalizeRichBody($data['body']);
        $this->linkGuard->assertAllowed($body);

        $excerpt = $this->sanitizer->excerpt($body);
        $slug = $this->slugs->uniqueTopicSlug($data['title']);

        $metaTitle = isset($data['meta_title']) ? Str::limit(trim(strip_tags($data['meta_title'])), 70) : null;
        $metaTitle = $metaTitle === '' ? null : $metaTitle;
        $metaDesc = isset($data['meta_description']) ? Str::limit(trim(strip_tags($data['meta_description'])), 200) : null;
        $metaDesc = $metaDesc === '' ? null : $metaDesc;

        $site = CommunitySiteMeta::singleton();
        $user = $request->user();
        $mod = CommunityTopic::MODERATION_APPROVED;
        if ($site->moderation_new_topics || $user->isCommunityShadowBanned()) {
            $mod = CommunityTopic::MODERATION_PENDING;
        }

        $topic = CommunityTopic::query()->create([
            'community_category_id' => $category->getKey(),
            'user_id' => $user->getKey(),
            'title' => $data['title'],
            'slug' => $slug,
            'body' => $body,
            'excerpt' => $excerpt,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDesc,
            'status' => CommunityTopic::STATUS_PUBLISHED,
            'moderation_status' => $mod,
            'last_activity_at' => now(),
        ]);

        CommunityTag::syncToTopic($topic, CommunityTag::parseNamesFromCsv($data['tags'] ?? null, 5), 5);

        $msg = $mod === CommunityTopic::MODERATION_PENDING
            ? 'Konu gönderildi; moderasyon onayından sonra herkese açılır.'
            : 'Konu oluşturuldu.';

        return redirect()->route('community.topic', $topic->slug)->with('status', $msg);
    }

    public function storeReply(Request $request, CommunityTopic $topic): RedirectResponse
    {
        if (! $this->userCanAccessTopicForParticipation($request, $topic)) {
            abort(404);
        }
        if ($topic->is_locked) {
            return back()->withErrors(['reply' => 'Bu konu kilitli.']);
        }

        $data = $request->validate([
            'hv_company' => ['nullable', 'string', 'max:0'],
            'body' => ['required', 'string', 'max:60000', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value)) {
                    $fail('Geçersiz içerik.');

                    return;
                }
                if (CommunityRichContent::isEffectivelyEmpty($value)) {
                    $fail('Yanıt boş olamaz.');

                    return;
                }
                if (CommunityRichContent::plainTextLength($value) < 2) {
                    $fail('Yanıt çok kısa.');
                }
            }],
        ]);

        $body = $this->sanitizer->finalizeRichBody($data['body']);
        $this->linkGuard->assertAllowed($body);

        $site = CommunitySiteMeta::singleton();
        $user = $request->user();
        $postMod = CommunityPost::MODERATION_APPROVED;
        if ($site->moderation_new_posts || $user->isCommunityShadowBanned()) {
            $postMod = CommunityPost::MODERATION_PENDING;
        }

        CommunityPost::query()->create([
            'community_topic_id' => $topic->getKey(),
            'user_id' => $user->getKey(),
            'body' => $body,
            'moderation_status' => $postMod,
        ]);

        $topic->update(['last_activity_at' => now()]);

        $msg = $postMod === CommunityPost::MODERATION_PENDING
            ? 'Yanıtınız alındı; onay sonrasında görünür olacak.'
            : 'Yanıt gönderildi.';

        return back()->with('status', $msg);
    }

    public function setBestAnswer(Request $request, CommunityTopic $topic): RedirectResponse
    {
        if (! $this->userCanAccessTopicForParticipation($request, $topic)) {
            abort(404);
        }
        if ((int) $topic->user_id !== (int) $request->user()->getKey()) {
            abort(403);
        }

        $data = $request->validate([
            'post_id' => 'required|exists:community_posts,id',
        ]);

        $post = CommunityPost::query()->whereKey($data['post_id'])->where('community_topic_id', $topic->getKey())->first();
        if (! $post || $post->is_hidden || $post->moderation_status !== CommunityPost::MODERATION_APPROVED) {
            return back()->withErrors(['post_id' => 'Geçersiz yanıt.']);
        }

        $topic->update([
            'best_answer_post_id' => $post->getKey(),
            'is_solved' => true,
            'last_activity_at' => now(),
        ]);

        return back()->with('status', 'En iyi yanıt işaretlendi.');
    }

    private function userCanAccessTopicForParticipation(Request $request, CommunityTopic $topic): bool
    {
        if ($topic->status !== CommunityTopic::STATUS_PUBLISHED || ! $topic->category?->is_active) {
            return false;
        }
        if ($topic->moderation_status === CommunityTopic::MODERATION_REJECTED) {
            return false;
        }
        if ($topic->moderation_status === CommunityTopic::MODERATION_APPROVED) {
            return true;
        }

        $u = $request->user();
        if (! $u) {
            return false;
        }

        return (int) $u->getKey() === (int) $topic->user_id;
    }
}
