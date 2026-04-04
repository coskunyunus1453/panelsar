<x-admin.layout title="Blog yazıları">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Yayın tarihi ve slug ile listelenir. SEO ve kategori alanları yazı düzenlemede.</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.blog-categories.index') }}" class="admin-btn-outline text-xs">Kategoriler</a>
            <a href="{{ route('admin.blog-posts.create') }}" class="admin-btn-emerald">Yeni yazı</a>
        </div>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
        <span>Dil:</span>
        <a href="{{ route('admin.blog-posts.index') }}" class="{{ request('locale') ? '' : 'font-semibold text-emerald-600 dark:text-emerald-400' }}">Tümü</a>
        @foreach (config('landing.locales') as $code => $label)
            <a href="{{ route('admin.blog-posts.index', ['locale' => $code]) }}" class="{{ request('locale') === $code ? 'font-semibold text-emerald-600 dark:text-emerald-400' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Dil</th>
                    <th class="px-4 py-3">Başlık</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Yayın</th>
                    <th class="px-4 py-3">Durum</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($posts as $post)
                    <tr class="admin-table-row">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $post->locale }}</td>
                        <td class="px-4 py-3">
                            <div class="admin-td-strong">{{ $post->title }}</div>
                            <div class="font-mono text-[11px] text-slate-500 dark:text-slate-500">{{ $post->slug }}</div>
                        </td>
                        <td class="admin-td-muted px-4 py-3 text-xs">
                            {{ $post->category?->name ?? '—' }}
                        </td>
                        <td class="admin-td-muted px-4 py-3 text-xs">
                            {{ $post->published_at?->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($post->is_published)
                                <span class="admin-badge-ok">Yayında</span>
                            @else
                                <span class="admin-badge-off">Taslak</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-xs">
                            <a href="{{ route('admin.blog-posts.edit', $post) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.blog-posts.destroy', $post) }}" method="POST" class="inline" onsubmit="return confirm('Silinsin mi?');">
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
        {{ $posts->links() }}
    </div>
</x-admin.layout>
