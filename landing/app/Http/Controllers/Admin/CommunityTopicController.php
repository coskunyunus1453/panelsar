<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityTag;
use App\Models\CommunityTopic;
use App\Services\Community\CommunityBodySanitizer;
use App\Services\Community\CommunitySlugService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityTopicController extends Controller
{
    public function __construct(
        private CommunityBodySanitizer $sanitizer,
        private CommunitySlugService $slugs,
    ) {}

    public function index(Request $request): View
    {
        $q = CommunityTopic::query()->with(['category', 'author']);

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('moderation')) {
            $q->where('moderation_status', $request->string('moderation'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', $term)->orWhere('body', 'like', $term);
            });
        }

        $topics = $q->orderByDesc('is_pinned')->orderByDesc('last_activity_at')->paginate(30)->withQueryString();

        return view('admin.community.topics.index', compact('topics'));
    }

    public function edit(CommunityTopic $community_topic): View
    {
        $community_topic->load(['category', 'author', 'tags', 'posts' => fn ($p) => $p->with('author')->orderBy('created_at')]);

        return view('admin.community.topics.edit', ['topic' => $community_topic]);
    }

    public function update(Request $request, CommunityTopic $community_topic): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|min:3|max:200',
            'slug' => 'sometimes|string|max:191|unique:community_topics,slug,'.$community_topic->getKey(),
            'body' => 'sometimes|string|min:10|max:60000',
            'status' => 'sometimes|in:published,hidden',
            'moderation_status' => 'sometimes|in:approved,pending,rejected',
            'tags_line' => 'nullable|string|max:2000',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:2000',
            'canonical_url' => 'nullable|string|max:2048',
            'robots_override' => 'nullable|in:index,noindex',
        ]);

        if (isset($data['body'])) {
            $data['body'] = $this->sanitizer->finalizeRichBody($data['body']);
            $data['excerpt'] = $this->sanitizer->excerpt($data['body']);
        }

        if (isset($data['slug'])) {
            $data['slug'] = $this->slugs->uniqueTopicSlug($data['slug'], (int) $community_topic->getKey());
        }

        $data['is_locked'] = $request->boolean('is_locked');
        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['is_solved'] = $request->boolean('is_solved');

        unset($data['tags_line']);
        $community_topic->update($data);

        if ($request->has('tags_line')) {
            CommunityTag::syncToTopic(
                $community_topic,
                CommunityTag::parseNamesFromCsv($request->input('tags_line'), 20),
                20
            );
        }

        return redirect()->route('admin.community.topics.edit', $community_topic)->with('status', 'Konu güncellendi.');
    }

    public function destroy(CommunityTopic $community_topic): RedirectResponse
    {
        $community_topic->delete();

        return redirect()->route('admin.community.topics.index')->with('status', 'Konu silindi.');
    }
}
