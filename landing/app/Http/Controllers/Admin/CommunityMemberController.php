<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityMemberController extends Controller
{
    public function index(Request $request): View
    {
        $q = User::query()
            ->where('is_admin', false)
            ->withCount([
                'communityTopics',
                'communityPosts',
            ]);

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        if ($request->string('activity') === 'active') {
            $q->where(function ($w) {
                $w->whereHas('communityTopics')->orWhereHas('communityPosts');
            });
        }

        if ($request->string('banned') === '1') {
            $q->whereNotNull('community_banned_at');
        } elseif ($request->string('banned') === '0') {
            $q->whereNull('community_banned_at');
        }

        $users = $q->orderByDesc('community_topics_count')
            ->orderByDesc('community_posts_count')
            ->orderByDesc('created_at')
            ->paginate(40)
            ->withQueryString();

        return view('admin.community.members.index', ['members' => $users]);
    }

    public function edit(User $user): RedirectResponse|View
    {
        if ($user->is_admin) {
            return redirect()
                ->route('admin.community.members.index')
                ->with('error', 'Yönetici hesapları bu listeden düzenlenmez.');
        }

        $user->loadCount(['communityTopics', 'communityPosts']);
        $recentTopics = $user->communityTopics()
            ->with('category')
            ->orderByDesc('last_activity_at')
            ->limit(15)
            ->get();

        $topicViewSum = (int) $user->communityTopics()->sum('view_count');
        $lastTopicActivity = $user->communityTopics()->max('last_activity_at');

        return view('admin.community.members.edit', [
            'member' => $user,
            'recentTopics' => $recentTopics,
            'topicViewSum' => $topicViewSum,
            'lastTopicActivity' => $lastTopicActivity,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->is_admin) {
            return redirect()->route('admin.community.members.index')->with('error', 'Yönetici hesabı güncellenemez.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
            'community_admin_notes' => ['nullable', 'string', 'max:20000'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'community_admin_notes' => $data['community_admin_notes'] !== null && $data['community_admin_notes'] !== ''
                ? trim($data['community_admin_notes'])
                : null,
        ])->save();

        return redirect()
            ->route('admin.community.members.edit', $user)
            ->with('status', 'Üye bilgileri güncellendi.');
    }

    public function ban(Request $request, User $user): RedirectResponse
    {
        if ($user->is_admin) {
            return redirect()->route('admin.community.members.index')->with('error', 'Yönetici yasaklanamaz.');
        }

        if ($user->getKey() === $request->user()?->getKey()) {
            return redirect()->back()->with('error', 'Kendi hesabınızı yasaklayamazsınız.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->forceFill([
            'community_banned_at' => now(),
            'community_ban_reason' => isset($data['reason']) ? Str::limit(trim($data['reason']), 500) : null,
        ])->save();

        return redirect()
            ->route('admin.community.members.edit', $user)
            ->with('status', 'Üye toplulukta yasaklandı.');
    }

    public function unban(User $user): RedirectResponse
    {
        if ($user->is_admin) {
            return redirect()->route('admin.community.members.index')->with('error', 'Geçersiz işlem.');
        }

        $user->forceFill([
            'community_banned_at' => null,
            'community_ban_reason' => null,
        ])->save();

        return redirect()
            ->route('admin.community.members.edit', $user)
            ->with('status', 'Topluluk yasağı kaldırıldı.');
    }

    public function shadowban(Request $request, User $user): RedirectResponse
    {
        if ($user->is_admin) {
            return redirect()->route('admin.community.members.index')->with('error', 'Geçersiz işlem.');
        }

        if ($user->getKey() === $request->user()?->getKey()) {
            return redirect()->back()->with('error', 'Kendi hesabınıza gölge yasağı uygulayamazsınız.');
        }

        $user->forceFill(['community_shadowbanned_at' => now()])->save();

        return redirect()
            ->route('admin.community.members.edit', $user)
            ->with('status', 'Gölge yasak etkin; üyenin yeni konu ve yanıtları moderasyon kuyruğuna düşer.');
    }

    public function unshadowban(User $user): RedirectResponse
    {
        if ($user->is_admin) {
            return redirect()->route('admin.community.members.index')->with('error', 'Geçersiz işlem.');
        }

        $user->forceFill(['community_shadowbanned_at' => null])->save();

        return redirect()
            ->route('admin.community.members.edit', $user)
            ->with('status', 'Gölge yasak kaldırıldı.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is_admin) {
            return redirect()->route('admin.community.members.index')->with('error', 'Yönetici silinemez.');
        }

        if ($user->getKey() === $request->user()?->getKey()) {
            return redirect()->back()->with('error', 'Kendi hesabınızı silemezsiniz.');
        }

        $user->delete();

        return redirect()
            ->route('admin.community.members.index')
            ->with('status', 'Üye ve topluluk içerikleri (konu/yanıt) silindi.');
    }
}
