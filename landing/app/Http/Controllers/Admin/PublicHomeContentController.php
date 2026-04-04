<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use App\Services\LandingAppearance;
use App\Services\LandingI18n;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PublicHomeContentController extends Controller
{
    public function edit(): View
    {
        $groups = config('landing_content_keys.groups', []);
        $allowedKeys = [];
        foreach ($groups as $labels) {
            $allowedKeys = array_merge($allowedKeys, array_keys($labels));
        }
        $overrides = LandingAppearance::pageOverrides();

        $rawCards = LandingSiteSetting::getValue('landing.home_feature_cards', '[]');
        $cards = json_decode((string) $rawCards, true);
        if (! is_array($cards)) {
            $cards = [];
        }
        if ($cards === []) {
            $cards = LandingAppearance::DEFAULT_FEATURE_CARDS;
        }

        return view('admin.public-home-content.edit', [
            'groups' => $groups,
            'allowedKeys' => $allowedKeys,
            'overrides' => $overrides,
            'featureCards' => $cards,
            'heroImageUrl' => LandingAppearance::heroImageUrl(),
            'heroImageAlt' => LandingAppearance::heroImageAlt(),
            'heroImageCaption' => LandingAppearance::heroImageCaption(),
            'icons' => config('landing_theme.feature_icons', []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $groups = config('landing_content_keys.groups', []);
        $allowedKeys = [];
        foreach ($groups as $labels) {
            $allowedKeys = array_merge($allowedKeys, array_keys($labels));
        }
        $iconKeys = array_keys(config('landing_theme.feature_icons', []));

        $rules = [
            'content' => ['nullable', 'array'],
            'feature_cards' => ['nullable', 'array', 'max:12'],
            'feature_cards.*.title' => ['nullable', 'string', 'max:500'],
            'feature_cards.*.body' => ['nullable', 'string', 'max:4000'],
            'feature_cards.*.icon' => ['nullable', Rule::in($iconKeys)],
            'hero_image' => ['nullable', 'image', 'max:5120'],
            'hero_image_alt' => ['nullable', 'string', 'max:500'],
            'hero_image_caption' => ['nullable', 'string', 'max:1000'],
            'remove_hero_image' => ['nullable', 'boolean'],
        ];
        foreach ($allowedKeys as $key) {
            $rules['content.'.$key] = ['nullable', 'string', 'max:65000'];
        }

        $validated = $request->validate($rules);

        $bag = [];
        $content = $validated['content'] ?? [];
        if (is_array($content)) {
            foreach ($allowedKeys as $key) {
                if (! array_key_exists($key, $content)) {
                    continue;
                }
                $val = $content[$key];
                if (! is_string($val)) {
                    continue;
                }
                $val = trim($val);
                if ($val !== '') {
                    $bag[$key] = $val;
                }
            }
        }
        LandingSiteSetting::put('landing.page_overrides', json_encode($bag, JSON_UNESCAPED_UNICODE));
        LandingI18n::clearRuntimeCache();

        $cardsOut = [];
        foreach ($validated['feature_cards'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $body = trim((string) ($row['body'] ?? ''));
            $icon = (string) ($row['icon'] ?? 'layers');
            if (! in_array($icon, $iconKeys, true)) {
                $icon = 'layers';
            }
            if ($title === '' && $body === '') {
                continue;
            }
            $cardsOut[] = ['title' => $title, 'body' => $body, 'icon' => $icon];
        }
        LandingSiteSetting::put('landing.home_feature_cards', json_encode($cardsOut, JSON_UNESCAPED_UNICODE));

        LandingSiteSetting::put('landing.hero_image_alt', trim((string) ($validated['hero_image_alt'] ?? '')));
        LandingSiteSetting::put('landing.hero_image_caption', trim((string) ($validated['hero_image_caption'] ?? '')));

        if ($request->boolean('remove_hero_image')) {
            $old = LandingSiteSetting::getValue('landing.hero_image_path', '');
            if (is_string($old) && $old !== '' && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            LandingSiteSetting::put('landing.hero_image_path', '');
        } elseif ($request->hasFile('hero_image')) {
            $file = $request->file('hero_image');
            if ($file !== null && $file->isValid()) {
                $old = LandingSiteSetting::getValue('landing.hero_image_path', '');
                if (is_string($old) && $old !== '' && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $path = $file->storeAs('landing', 'hero-'.time().'.'.$ext, 'public');
                LandingSiteSetting::put('landing.hero_image_path', $path);
            }
        }

        return back()->with('status', 'Ön yüz içeriği güncellendi.');
    }
}
