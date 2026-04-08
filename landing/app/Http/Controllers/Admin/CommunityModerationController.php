<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CommunityModerationController extends Controller
{
    public function index(): View
    {
        $pendingTopics = CommunityTopic::query()
            ->with(['category', 'author'])
            ->where('status', CommunityTopic::STATUS_PUBLISHED)
            ->where('moderation_status', CommunityTopic::MODERATION_PENDING)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $pendingPosts = CommunityPost::query()
            ->with(['author', 'topic' => fn ($q) => $q->with('category')])
            ->where('moderation_status', CommunityPost::MODERATION_PENDING)
            ->where('is_hidden', false)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('admin.community.moderation.index', [
            'pendingTopics' => $pendingTopics,
            'pendingPosts' => $pendingPosts,
        ]);
    }

    public function approveTopic(Request $request, CommunityTopic $community_topic): RedirectResponse
    {
        if ($community_topic->moderation_status !== CommunityTopic::MODERATION_PENDING) {
            return back()->with('error', 'Bu konu bekleyen durumda değil.');
        }

        $community_topic->update(['moderation_status' => CommunityTopic::MODERATION_APPROVED]);

        CommunityPost::query()
            ->where('community_topic_id', $community_topic->getKey())
            ->where('moderation_status', CommunityPost::MODERATION_PENDING)
            ->update(['moderation_status' => CommunityPost::MODERATION_APPROVED]);

        Log::info('community.moderation', [
            'action' => 'topic_approve',
            'topic_id' => $community_topic->getKey(),
            'admin_id' => $request->user()?->getKey(),
        ]);

        return back()->with('status', 'Konu onaylandı.');
    }

    public function rejectTopic(Request $request, CommunityTopic $community_topic): RedirectResponse
    {
        if ($community_topic->moderation_status !== CommunityTopic::MODERATION_PENDING) {
            return back()->with('error', 'Bu konu bekleyen durumda değil.');
        }

        $community_topic->update(['moderation_status' => CommunityTopic::MODERATION_REJECTED]);

        Log::warning('community.moderation', [
            'action' => 'topic_reject',
            'topic_id' => $community_topic->getKey(),
            'admin_id' => $request->user()?->getKey(),
        ]);

        return back()->with('status', 'Konu reddedildi (yayından kalktı).');
    }

    public function approvePost(Request $request, CommunityPost $community_post): RedirectResponse
    {
        if ($community_post->moderation_status !== CommunityPost::MODERATION_PENDING) {
            return back()->with('error', 'Bu yanıt bekleyen durumda değil.');
        }

        $community_post->update(['moderation_status' => CommunityPost::MODERATION_APPROVED]);
        if ($community_post->topic) {
            $community_post->topic->update(['last_activity_at' => now()]);
        }

        Log::info('community.moderation', [
            'action' => 'post_approve',
            'post_id' => $community_post->getKey(),
            'topic_id' => $community_post->community_topic_id,
            'admin_id' => $request->user()?->getKey(),
        ]);

        return back()->with('status', 'Yanıt onaylandı.');
    }

    public function rejectPost(Request $request, CommunityPost $community_post): RedirectResponse
    {
        if ($community_post->moderation_status !== CommunityPost::MODERATION_PENDING) {
            return back()->with('error', 'Bu yanıt bekleyen durumda değil.');
        }

        $topic = $community_post->topic;
        $community_post->update([
            'moderation_status' => CommunityPost::MODERATION_REJECTED,
            'is_hidden' => true,
        ]);

        if ($topic instanceof CommunityTopic && (int) $topic->best_answer_post_id === (int) $community_post->getKey()) {
            $topic->update(['best_answer_post_id' => null, 'is_solved' => false]);
        }

        Log::warning('community.moderation', [
            'action' => 'post_reject',
            'post_id' => $community_post->getKey(),
            'topic_id' => $community_post->community_topic_id,
            'admin_id' => $request->user()?->getKey(),
        ]);

        return back()->with('status', 'Yanıt reddedildi ve gizlendi.');
    }
}
