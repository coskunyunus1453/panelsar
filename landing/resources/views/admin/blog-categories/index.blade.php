<x-admin.layout title="Blog kategorileri">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Kategori slug’ları URL’de <code class="text-xs">/blog/category/slug</code> olarak kullanılır (İngilizce yol). İçerik dili kayıttaki locale ile seçilir.</p>
        <a href="{{ route('admin.blog-categories.create') }}" class="admin-btn-emerald">Yeni kategori</a>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
        <span>Dil:</span>
        <a href="{{ route('admin.blog-categories.index') }}" class="{{ request('locale') ? '' : 'font-semibold text-emerald-600 dark:text-emerald-400' }}">Tümü</a>
        @foreach (config('landing.locales') as $code => $label)
            <a href="{{ route('admin.blog-categories.index', ['locale' => $code]) }}" class="{{ request('locale') === $code ? 'font-semibold text-emerald-600 dark:text-emerald-400' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Dil</th>
                    <th class="px-4 py-3">Sıra</th>
                    <th class="px-4 py-3">Ad</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($categories as $cat)
                    <tr class="admin-table-row">
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $cat->locale }}</td>
                        <td class="admin-td-muted px-4 py-2">{{ $cat->sort_order }}</td>
                        <td class="admin-td-strong px-4 py-2">{{ $cat->name }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-600 dark:text-slate-400">{{ $cat->slug }}</td>
                        <td class="px-4 py-2 text-right text-xs">
                            <a href="{{ route('admin.blog-categories.edit', $cat) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.blog-categories.destroy', $cat) }}" method="POST" class="inline" onsubmit="return confirm('Silinsin mi? Yazılardaki kategori bağlantısı kaldırılır.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $categories->links() }}
    </div>
</x-admin.layout>
