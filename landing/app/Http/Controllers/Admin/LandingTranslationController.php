<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use App\Models\LandingTranslation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;

class LandingTranslationController extends Controller
{
    public function index(Request $request): View
    {
        $locale = (string) $request->query('locale', 'tr');
        $enabled = json_decode(LandingSiteSetting::getValue('landing.enabled_locales', '["tr","en"]'), true) ?: ['tr', 'en'];
        if (! in_array($locale, $enabled, true)) {
            $locale = $enabled[0];
        }

        $tree = Lang::get('landing', [], 'tr');
        $tree = is_array($tree) ? $tree : [];
        $flat = Arr::dot($tree);

        $rows = [];
        foreach ($flat as $key => $_trString) {
            if (! is_string($_trString)) {
                continue;
            }
            $current = trans('landing.'.$key, [], $locale);
            $override = LandingTranslation::query()->where('locale', $locale)->where('key', $key)->value('value');
            $rows[] = [
                'key' => $key,
                'base_tr' => $_trString,
                'effective' => ($override !== null && $override !== '') ? $override : (is_string($current) ? $current : ''),
                'has_override' => $override !== null && $override !== '',
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['key'], $b['key']));

        return view('admin.translations.index', [
            'rows' => $rows,
            'locale' => $locale,
            'enabledLocales' => $enabled,
            'localeLabels' => config('landing.locales', []),
        ]);
    }

    public function edit(Request $request): View
    {
        $key = (string) $request->query('key', '');
        $locale = (string) $request->query('locale', 'tr');
        abort_if($key === '' || ! self::isValidTranslationKey($key), 404);

        $fileVal = trans('landing.'.$key, [], $locale);
        $fileVal = is_string($fileVal) ? $fileVal : '';

        $override = LandingTranslation::query()
            ->where('locale', $locale)
            ->where('key', $key)
            ->value('value');

        return view('admin.translations.edit', [
            'key' => $key,
            'locale' => $locale,
            'fileVal' => $fileVal,
            'override' => $override,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $enabled = json_decode(LandingSiteSetting::getValue('landing.enabled_locales', '["tr","en"]'), true) ?: ['tr', 'en'];

        $base = $request->validate([
            'key' => ['required', 'string', 'max:190', 'regex:/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*$/'],
            'locale' => ['required', 'string', 'max:16', Rule::in($enabled)],
        ]);

        if ($request->has('reset')) {
            LandingTranslation::query()
                ->where('locale', $base['locale'])
                ->where('key', $base['key'])
                ->delete();

            return redirect()
                ->route('admin.translations.index', ['locale' => $base['locale']])
                ->with('status', 'Çeviri dosya varsayılana sıfırlandı.');
        }

        $data = $request->validate([
            'value' => 'nullable|string|max:65535',
        ]);

        $val = (string) ($data['value'] ?? '');
        if ($val === '') {
            LandingTranslation::query()
                ->where('locale', $base['locale'])
                ->where('key', $base['key'])
                ->delete();

            return redirect()
                ->route('admin.translations.index', ['locale' => $base['locale']])
                ->with('status', 'Özel çeviri kaldırıldı (dil dosyası kullanılır).');
        }

        LandingTranslation::query()->updateOrCreate(
            ['locale' => $base['locale'], 'key' => $base['key']],
            ['value' => $val]
        );

        return redirect()
            ->route('admin.translations.index', ['locale' => $base['locale']])
            ->with('status', 'Çeviri kaydedildi.');
    }

    private static function isValidTranslationKey(string $key): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*$/', $key);
    }
}
