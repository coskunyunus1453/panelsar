@php
    use App\Models\NavMenuItem;
@endphp
<x-admin.layout title="Ön yüz menüleri">
    <p class="admin-muted max-w-2xl">
        Üst başlık ve alt bilgi bağlantıları. Site içi adresler <code class="rounded bg-slate-200 px-1 text-xs dark:bg-slate-800">/</code> ile başlamalı (ör. <code class="rounded bg-slate-200 px-1 text-xs dark:bg-slate-800">/blog</code>, <code class="rounded bg-slate-200 px-1 text-xs dark:bg-slate-800">/#faq</code>). Harici sayfa için tam <code class="text-xs">https://</code> adresi kullanın.
    </p>

    <div class="mt-8 space-y-10">
        <section>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="admin-label-block text-base">Üst menü</h2>
                <a href="{{ route('admin.nav-menu.create', ['zone' => NavMenuItem::ZONE_HEADER]) }}" class="admin-btn-emerald w-fit">Yeni bağlantı</a>
            </div>

            @if ($headerItems->isEmpty())
                <p class="admin-muted mt-4">Henüz kayıt yok. Varsayılan menüyü yüklemek için <code class="text-xs">php artisan db:seed --class=NavMenuSeeder</code> çalıştırabilir veya yeni bağlantı ekleyebilirsiniz.</p>
            @else
                <form method="post" action="{{ route('admin.nav-menu.reorder') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="zone" value="{{ NavMenuItem::ZONE_HEADER }}">
                    <div class="admin-table-wrap !mt-0">
                        <table class="min-w-full text-left text-sm">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="px-4 py-3">Sıra</th>
                                    <th class="px-4 py-3">Etiket</th>
                                    <th class="px-4 py-3">Bağlantı</th>
                                    <th class="px-4 py-3">Durum</th>
                                    <th class="px-4 py-3 text-right">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @foreach ($headerItems as $i => $row)
                                    <tr class="admin-table-row">
                                        <td class="px-4 py-2">
                                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $row->id }}">
                                            <input type="number" name="items[{{ $i }}][sort_order]" value="{{ $row->sort_order }}" min="0" max="500"
                                                   class="admin-field w-20 px-2 py-1.5 text-sm" />
                                        </td>
                                        <td class="admin-td-strong px-4 py-2">{{ $row->label }}</td>
                                        <td class="admin-td-muted px-4 py-2 font-mono text-xs">{{ $row->href }}</td>
                                        <td class="px-4 py-2 text-xs">
                                            @if ($row->is_active)
                                                <span class="admin-badge-ok">Açık</span>
                                            @else
                                                <span class="admin-badge-off">Kapalı</span>
                                            @endif
                                            @if ($row->open_in_new_tab)
                                                <span class="ml-1 text-slate-500">yeni sekme</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right text-xs">
                                            <a href="{{ route('admin.nav-menu.edit', $row) }}" class="admin-link-emerald">Düzenle</a>
                                            <button type="submit" form="nav-del-header-{{ $row->id }}" class="ml-3 text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300" onclick="return confirm('Silinsin mi?');">Sil</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="admin-btn-outline mt-3 text-xs">Üst menü sırasını kaydet</button>
                </form>
                @foreach ($headerItems as $row)
                    <form id="nav-del-header-{{ $row->id }}" method="post" action="{{ route('admin.nav-menu.destroy', $row) }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            @endif
        </section>

        <section>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="admin-label-block text-base">Alt menü (footer)</h2>
                <a href="{{ route('admin.nav-menu.create', ['zone' => NavMenuItem::ZONE_FOOTER]) }}" class="admin-btn-emerald w-fit">Yeni bağlantı</a>
            </div>

            @if ($footerItems->isEmpty())
                <p class="admin-muted mt-4">Henüz kayıt yok.</p>
            @else
                <form method="post" action="{{ route('admin.nav-menu.reorder') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="zone" value="{{ NavMenuItem::ZONE_FOOTER }}">
                    <div class="admin-table-wrap !mt-0">
                        <table class="min-w-full text-left text-sm">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="px-4 py-3">Sıra</th>
                                    <th class="px-4 py-3">Etiket</th>
                                    <th class="px-4 py-3">Bağlantı</th>
                                    <th class="px-4 py-3">Durum</th>
                                    <th class="px-4 py-3 text-right">İşlem</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @foreach ($footerItems as $i => $row)
                                    <tr class="admin-table-row">
                                        <td class="px-4 py-2">
                                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $row->id }}">
                                            <input type="number" name="items[{{ $i }}][sort_order]" value="{{ $row->sort_order }}" min="0" max="500"
                                                   class="admin-field w-20 px-2 py-1.5 text-sm" />
                                        </td>
                                        <td class="admin-td-strong px-4 py-2">{{ $row->label }}</td>
                                        <td class="admin-td-muted px-4 py-2 font-mono text-xs">{{ $row->href }}</td>
                                        <td class="px-4 py-2 text-xs">
                                            @if ($row->is_active)
                                                <span class="admin-badge-ok">Açık</span>
                                            @else
                                                <span class="admin-badge-off">Kapalı</span>
                                            @endif
                                            @if ($row->open_in_new_tab)
                                                <span class="ml-1 text-slate-500">yeni sekme</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right text-xs">
                                            <a href="{{ route('admin.nav-menu.edit', $row) }}" class="admin-link-emerald">Düzenle</a>
                                            <button type="submit" form="nav-del-footer-{{ $row->id }}" class="ml-3 text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300" onclick="return confirm('Silinsin mi?');">Sil</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="admin-btn-outline mt-3 text-xs">Alt menü sırasını kaydet</button>
                </form>
                @foreach ($footerItems as $row)
                    <form id="nav-del-footer-{{ $row->id }}" method="post" action="{{ route('admin.nav-menu.destroy', $row) }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            @endif
        </section>
    </div>
</x-admin.layout>
