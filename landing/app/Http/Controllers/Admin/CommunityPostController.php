<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Services\Community\CommunityBodySanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityPostController extends Controller
{
    public function __construct(
        private CommunityBodySanitizer $sanitizer,
    ) {}

    public function update(Request $request, CommunityPost $community_post): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'sometimes|string|min:2|max:60000',
            'moderation_status' => 'sometimes|in:approved,pending,rejected',
        ]);

        if (isset($data['body'])) {
            $data['body'] = $this->sanitizer->finalizeRichBody($data['body']);
        }

        $data['is_hidden'] = $request->boolean('is_hidden');

        $community_post->update($data);

        if ($community_post->topic) {
            $community_post->topic->update(['last_activity_at' => now()]);
        }

        return back()->with('status', 'Yanıt güncellendi.');
    }

    public function destroy(CommunityPost $community_post): RedirectResponse
    {
        $topic = $community_post->topic;
        $community_post->delete();

        if ($topic instanceof CommunityTopic && (int) $topic->best_answer_post_id === (int) $community_post->getKey()) {
            $topic->update(['best_answer_post_id' => null, 'is_solved' => false]);
        }

        return back()->with('status', 'Yanıt silindi.');
    }
}
