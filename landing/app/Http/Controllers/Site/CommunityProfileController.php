<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CommunitySiteMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $site = CommunitySiteMeta::singleton();

        return view('site.community.profile', [
            'site' => $site,
            'seoTitle' => 'Topluluk profili — '.$site->site_title,
            'seoDescription' => 'Görünen ad ve güvenli HTTPS profil görseli.',
            'canonicalUrl' => route('community.profile.edit', absolute: true),
            'robotsContent' => 'noindex, follow',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'avatar_url' => ['nullable', 'string', 'max:512'],
        ]);

        $avatar = isset($data['avatar_url']) ? trim((string) $data['avatar_url']) : '';
        if ($avatar !== '') {
            if (! str_starts_with($avatar, 'https://') || preg_match('/[\s<>"\'\\\\]/', $avatar)) {
                return back()->withErrors(['avatar_url' => 'Yalnızca güvenli (HTTPS) görsel adresi kullanın.'])->withInput();
            }
            if (filter_var($avatar, FILTER_VALIDATE_URL) === false) {
                return back()->withErrors(['avatar_url' => 'Geçerli bir adres girin.'])->withInput();
            }
        }

        $user = $request->user();
        $user->forceFill([
            'name' => $data['name'],
            'avatar_url' => $avatar !== '' ? $avatar : null,
        ])->save();

        return redirect()->route('community.profile.edit')->with('status', 'Profiliniz güncellendi.');
    }
}
