<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleSettingsController extends Controller
{
    public function edit(): View
    {
        $default = LandingSiteSetting::getValue('landing.default_locale', 'tr');
        $enabled = json_decode(LandingSiteSetting::getValue('landing.enabled_locales', '["tr","en"]'), true) ?: ['tr', 'en'];
        $locales = config('landing.locales', []);

        return view('admin.locale-settings.edit', compact('default', 'enabled', 'locales'));
    }

    public function update(Request $request): RedirectResponse
    {
        $codes = implode(',', array_keys(config('landing.locales', [])));
        $validated = $request->validate([
            'default_locale' => 'required|in:'.$codes,
            'enabled_locales' => 'required|array|min:1',
            'enabled_locales.*' => 'in:'.$codes,
        ]);

        if (! in_array($validated['default_locale'], $validated['enabled_locales'], true)) {
            return back()->with('error', 'Varsayılan dil, etkin diller listesinde olmalıdır.')->withInput();
        }

        LandingSiteSetting::put('landing.default_locale', $validated['default_locale']);
        LandingSiteSetting::put('landing.enabled_locales', json_encode(array_values(array_unique($validated['enabled_locales']))));

        return back()->with('status', 'Dil ayarları güncellendi.');
    }
}
