<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingSiteSetting;
use App\Services\LandingAppearance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeSettingsController extends Controller
{
    public function edit(): View
    {
        $iconKeys = array_keys(config('landing_theme.feature_icons', []));

        return view('admin.theme-settings.edit', [
            'activeTheme' => LandingAppearance::activeTheme(),
            'graphicMotif' => LandingAppearance::graphicMotif(),
            'primaryHex' => LandingSiteSetting::getValue('landing.theme_primary_hex', '') ?? '',
            'themes' => config('landing_theme.themes', []),
            'motifs' => config('landing_theme.graphic_motifs', []),
            'featureIcons' => config('landing_theme.feature_icons', []),
            'neonTop' => LandingAppearance::neonTop(),
            'neonStackSection' => LandingAppearance::neonStackSection(),
            'neonStackItems' => LandingAppearance::neonStackItems(),
            'neonGridSection' => LandingAppearance::neonGridSection(),
            'neonGridItems' => LandingAppearance::neonGridItems(),
            'iconKeys' => $iconKeys,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $motifKeys = array_keys(config('landing_theme.graphic_motifs', []));
        $iconKeys = array_keys(config('landing_theme.feature_icons', []));

        $validated = $request->validate([
            'active_theme' => ['required', Rule::in(['orange', 'turquoise', 'neon'])],
            'graphic_motif' => ['required', Rule::in($motifKeys)],
            'theme_primary_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme_neon_top' => ['nullable', 'array'],
            'theme_neon_top.badge' => ['nullable', 'string', 'max:160'],
            'theme_neon_top.title' => ['nullable', 'string', 'max:300'],
            'theme_neon_top.lead' => ['nullable', 'string', 'max:2000'],
            'theme_neon_top.cta_primary' => ['nullable', 'string', 'max:120'],
            'theme_neon_top.cta_secondary' => ['nullable', 'string', 'max:120'],
            'theme_neon_stack_section' => ['nullable', 'array'],
            'theme_neon_stack_section.title' => ['nullable', 'string', 'max:300'],
            'theme_neon_stack_section.lead' => ['nullable', 'string', 'max:2000'],
            'theme_neon_stack' => ['nullable', 'array'],
            'theme_neon_stack.*.title' => ['nullable', 'string', 'max:300'],
            'theme_neon_stack.*.body' => ['nullable', 'string', 'max:4000'],
            'theme_neon_stack.*.icon' => ['nullable', Rule::in($iconKeys)],
            'theme_neon_grid_section' => ['nullable', 'array'],
            'theme_neon_grid_section.title' => ['nullable', 'string', 'max:300'],
            'theme_neon_grid_section.lead' => ['nullable', 'string', 'max:2000'],
            'theme_neon_grid' => ['nullable', 'array'],
            'theme_neon_grid.*.title' => ['nullable', 'string', 'max:300'],
            'theme_neon_grid.*.body' => ['nullable', 'string', 'max:4000'],
            'theme_neon_grid.*.icon' => ['nullable', Rule::in($iconKeys)],
        ]);

        LandingSiteSetting::put('landing.active_theme', $validated['active_theme']);
        LandingSiteSetting::put('landing.graphic_motif', $validated['graphic_motif']);
        LandingSiteSetting::put('landing.theme_primary_hex', $validated['theme_primary_hex'] ?? '');

        $top = $request->input('theme_neon_top', []);
        LandingSiteSetting::put('landing.theme_neon_top', json_encode([
            'badge' => trim((string) ($top['badge'] ?? '')),
            'title' => trim((string) ($top['title'] ?? '')),
            'lead' => trim((string) ($top['lead'] ?? '')),
            'cta_primary' => trim((string) ($top['cta_primary'] ?? '')),
            'cta_secondary' => trim((string) ($top['cta_secondary'] ?? '')),
        ], JSON_UNESCAPED_UNICODE));

        $stackSec = $request->input('theme_neon_stack_section', []);
        LandingSiteSetting::put('landing.theme_neon_stack_section', json_encode([
            'title' => trim((string) ($stackSec['title'] ?? '')),
            'lead' => trim((string) ($stackSec['lead'] ?? '')),
        ], JSON_UNESCAPED_UNICODE));

        $stack = $this->normalizeNeonRows($request->input('theme_neon_stack', []), 5);
        LandingSiteSetting::put('landing.theme_neon_stack', json_encode($stack, JSON_UNESCAPED_UNICODE));

        $gridSec = $request->input('theme_neon_grid_section', []);
        LandingSiteSetting::put('landing.theme_neon_grid_section', json_encode([
            'title' => trim((string) ($gridSec['title'] ?? '')),
            'lead' => trim((string) ($gridSec['lead'] ?? '')),
        ], JSON_UNESCAPED_UNICODE));

        $grid = $this->normalizeNeonRows($request->input('theme_neon_grid', []), 6);
        LandingSiteSetting::put('landing.theme_neon_grid', json_encode($grid, JSON_UNESCAPED_UNICODE));

        return back()->with('status', 'Tema ve görünüm ayarları kaydedildi.');
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array{title: string, body: string, icon: string}>
     */
    private function normalizeNeonRows(array $rows, int $count): array
    {
        $iconKeys = array_keys(config('landing_theme.feature_icons', []));
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $r = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            $icon = isset($r['icon']) && is_string($r['icon']) && in_array($r['icon'], $iconKeys, true)
                ? $r['icon']
                : 'layers';
            $out[] = [
                'title' => trim((string) ($r['title'] ?? '')),
                'body' => trim((string) ($r['body'] ?? '')),
                'icon' => $icon,
            ];
        }

        return $out;
    }
}
