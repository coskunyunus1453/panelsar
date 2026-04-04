<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavMenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NavMenuItemController extends Controller
{
    public function index(): View
    {
        $headerItems = NavMenuItem::query()->where('zone', NavMenuItem::ZONE_HEADER)->orderBy('sort_order')->orderBy('id')->get();
        $footerItems = NavMenuItem::query()->where('zone', NavMenuItem::ZONE_FOOTER)->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.nav-menu.index', [
            'headerItems' => $headerItems,
            'footerItems' => $footerItems,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $zone = $request->query('zone');
        if (! in_array($zone, [NavMenuItem::ZONE_HEADER, NavMenuItem::ZONE_FOOTER], true)) {
            return redirect()->route('admin.nav-menu.index')->with('error', 'Geçerli bir bölge seçin (üst veya alt menü).');
        }

        return view('admin.nav-menu.create', ['zone' => $zone]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, false);
        $next = (int) NavMenuItem::query()->where('zone', $data['zone'])->max('sort_order') + 1;
        $data['sort_order'] = $next;
        NavMenuItem::query()->create($data);

        return redirect()->route('admin.nav-menu.index')->with('status', 'Menü öğesi eklendi.');
    }

    public function edit(NavMenuItem $item): View
    {
        return view('admin.nav-menu.edit', ['item' => $item]);
    }

    public function update(Request $request, NavMenuItem $item): RedirectResponse
    {
        $item->update($this->validated($request, true));

        return redirect()->route('admin.nav-menu.index')->with('status', 'Menü öğesi güncellendi.');
    }

    public function destroy(NavMenuItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('admin.nav-menu.index')->with('status', 'Menü öğesi silindi.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'zone' => ['required', Rule::in([NavMenuItem::ZONE_HEADER, NavMenuItem::ZONE_FOOTER])],
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:nav_menu_items,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        foreach ($validated['items'] as $row) {
            NavMenuItem::query()->whereKey($row['id'])->where('zone', $validated['zone'])->update([
                'sort_order' => $row['sort_order'],
            ]);
        }

        return redirect()->route('admin.nav-menu.index')->with('status', 'Sıra kaydedildi.');
    }

    /**
     * @return array{zone: string, label: string, href: string, is_active: bool, open_in_new_tab: bool, sort_order?: int}
     */
    private function validated(Request $request, bool $withSortOrder): array
    {
        $rules = [
            'zone' => ['required', Rule::in([NavMenuItem::ZONE_HEADER, NavMenuItem::ZONE_FOOTER])],
            'label' => ['required', 'string', 'max:255'],
            'href' => ['required', 'string', 'max:2048', $this->hrefRule()],
            'is_active' => ['sometimes', 'boolean'],
            'open_in_new_tab' => ['sometimes', 'boolean'],
        ];

        if ($withSortOrder) {
            $rules['sort_order'] = ['required', 'integer', 'min:0', 'max:500'];
        }

        $data = $request->validate($rules);
        $data['is_active'] = $request->boolean('is_active');
        $data['open_in_new_tab'] = $request->boolean('open_in_new_tab');

        return $data;
    }

    private function hrefRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value)) {
                $fail('Bağlantı metin olmalıdır.');

                return;
            }
            $v = trim($value);
            if ($v === '') {
                $fail('Bağlantı boş olamaz.');

                return;
            }
            if (preg_match('#^https?://#i', $v)) {
                if (filter_var($v, FILTER_VALIDATE_URL) === false) {
                    $fail('Geçerli bir tam adres girin (https://...).');
                }

                return;
            }
            if (! str_starts_with($v, '/')) {
                $fail('Site içi yol / ile başlamalı (ör. /blog) veya tam adres kullanın.');

                return;
            }
            if (str_contains($v, '..')) {
                $fail('Geçersiz yol.');
            }
        };
    }
}
