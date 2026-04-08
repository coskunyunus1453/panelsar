<x-admin.layout title="Topluluk kategorileri">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Herkese açık forumda kullanılacak kategoriler. Slug URL’de <code class="text-xs">/community/c/slug</code> olarak kullanılır.</p>
        <a href="{{ route('admin.community.categories.create') }}" class="admin-btn-emerald">Yeni kategori</a>
    </div>
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-100">{{ session('error') }}</div>
    @endif

    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Sıra</th>
                    <th class="px-4 py-3">Ad</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Aktif</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($categories as $cat)
                    <tr class="admin-table-row">
                        <td class="px-4 py-2">{{ $cat->sort_order }}</td>
                        <td class="admin-td-strong px-4 py-2">{{ $cat->name }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $cat->slug }}</td>
                        <td class="px-4 py-2">{{ $cat->is_active ? 'Evet' : 'Hayır' }}</td>
                        <td class="px-4 py-2 text-right text-xs">
                            <a href="{{ route('admin.community.categories.edit', $cat) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.community.categories.destroy', $cat) }}" method="post" class="inline" onsubmit="return confirm('Silinsin mi?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-rose-600 hover:underline dark:text-rose-400">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.layout>
