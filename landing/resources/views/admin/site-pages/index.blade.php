<x-admin.layout title="Site sayfaları">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Zengin metin ile yeni sayfa ekleyin; SEO alanları düzenleme ekranında. Genel adres: <span class="font-mono">/p/slug</span> (slug <span class="font-mono">setup</span> → <span class="font-mono">/setup</span>). URL yolları İngilizce; içerik dili kayıttaki <span class="font-mono">locale</span> ile eşleşir.</p>
        <a href="{{ route('admin.site-pages.create') }}" class="admin-btn-emerald">
            Yeni sayfa
        </a>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
        <span>Dil:</span>
        <a href="{{ route('admin.site-pages.index') }}" class="{{ request('locale') ? '' : 'font-semibold text-emerald-600 dark:text-emerald-400' }}">Tümü</a>
        @foreach (config('landing.locales') as $code => $label)
            <a href="{{ route('admin.site-pages.index', ['locale' => $code]) }}" class="{{ request('locale') === $code ? 'font-semibold text-emerald-600 dark:text-emerald-400' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Dil</th>
                    <th class="px-4 py-3">Başlık</th>
                    <th class="px-4 py-3">Slug / adres</th>
                    <th class="px-4 py-3">Durum</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($pages as $page)
                    <tr class="admin-table-row">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $page->locale }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-200">{{ $page->title }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                            <span class="font-mono text-slate-500">{{ $page->slug }}</span>
                            @if ($page->is_published)
                                <a href="{{ $page->publicPath() }}" target="_blank" rel="noopener" class="ml-2 font-mono text-orange-700 hover:underline dark:text-orange-300">{{ $page->publicPath() }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($page->is_published)
                                <span class="admin-badge-ok">Yayında</span>
                            @else
                                <span class="admin-badge-off">Taslak</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-xs">
                            <a href="{{ route('admin.site-pages.edit', $page) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.site-pages.destroy', $page) }}" method="POST" class="inline" onsubmit="return confirm('Silinsin mi?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-rose-600 hover:text-rose-800 dark:text-rose-400/90 dark:hover:text-rose-300">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $pages->links() }}
    </div>
</x-admin.layout>
