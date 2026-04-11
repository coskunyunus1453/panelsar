<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommunitySiteMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunitySiteMetaController extends Controller
{
    public function edit(): View
    {
        return view('admin.community.settings', [
            'meta' => CommunitySiteMeta::singleton(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_title' => 'sometimes|string|max:255',
            'site_title_en' => 'nullable|string|max:255',
            'default_meta_title' => 'nullable|string|max:255',
            'default_meta_title_en' => 'nullable|string|max:255',
            'default_meta_description' => 'nullable|string|max:2000',
            'default_meta_description_en' => 'nullable|string|max:2000',
            'og_image_url' => 'nullable|string|max:2048',
            'twitter_site' => 'nullable|string|max:64',
        ]);
        $data['enable_indexing'] = $request->boolean('enable_indexing');
        $data['moderation_new_topics'] = $request->boolean('moderation_new_topics');
        $data['moderation_new_posts'] = $request->boolean('moderation_new_posts');

        CommunitySiteMeta::singleton()->update($data);

        return redirect()->route('admin.community.settings.edit')->with('status', 'Topluluk SEO ayarları kaydedildi.');
    }
}
