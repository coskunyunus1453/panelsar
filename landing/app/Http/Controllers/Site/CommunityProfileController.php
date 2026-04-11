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
            'seoTitle' => landing_t('community.profile_meta_title', ['site' => $site->displaySiteTitle()]),
            'seoDescription' => landing_t('community.profile_meta_description'),
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
                return back()->withErrors(['avatar_url' => landing_t('community.validation_avatar_https')])->withInput();
            }
            if (filter_var($avatar, FILTER_VALIDATE_URL) === false) {
                return back()->withErrors(['avatar_url' => landing_t('community.validation_avatar_url')])->withInput();
            }
        }

        $user = $request->user();
        $user->forceFill([
            'name' => $data['name'],
            'avatar_url' => $avatar !== '' ? $avatar : null,
        ])->save();

        return redirect()->route('community.profile.edit')->with('status', landing_t('community.flash_profile_updated'));
    }
}
