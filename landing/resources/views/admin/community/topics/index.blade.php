<x-admin.layout title="Topluluk konuları">
    <form method="get" class="mb-6 flex flex-wrap gap-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Ara…" class="min-w-[200px] rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
        <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <option value="">Tüm durumlar</option>
            <option value="published" @selected(request('status') === 'published')>published</option>
            <option value="hidden" @selected(request('status') === 'hidden')>hidden</option>
        </select>
        <select name="moderation" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <option value="">Tüm moderasyon</option>
            <option value="approved" @selected(request('moderation') === 'approved')>approved</option>
            <option value="pending" @selected(request('moderation') === 'pending')>pending</option>
            <option value="rejected" @selected(request('moderation') === 'rejected')>rejected</option>
        </select>
        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white dark:bg-slate-100 dark:text-slate-900">Filtrele</button>
    </form>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
    @endif

    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Başlık</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Açan üye</th>
                    <th class="px-4 py-3">Durum</th>
                    <th class="px-4 py-3">Mod.</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($topics as $topic)
                    <tr class="admin-table-row">
                        <td class="admin-td-strong px-4 py-2">{{ $topic->title }}</td>
                        <td class="px-4 py-2">{{ $topic->category?->name }}</td>
                        <td class="px-4 py-2">
                            @if ($topic->author)
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $topic->author->name }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $topic->author->email }}</div>
                                @if (! $topic->author->is_admin)
                                    <a href="{{ route('admin.community.members.edit', $topic->author) }}" class="admin-link-emerald text-xs">Üye kartı</a>
                                @else
                                    <span class="text-xs text-slate-400">Yönetici</span>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $topic->status }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $topic->moderation_status ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.community.topics.edit', $topic) }}" class="admin-link-emerald">Moderasyon</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $topics->links() }}</div>
</x-admin.layout>
