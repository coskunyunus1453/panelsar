<x-admin.layout title="Topluluk üyeleri">
    <form method="get" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Ara</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Ad veya e-posta…" class="min-w-[200px] rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Aktivite</label>
            <select name="activity" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="" @selected(request('activity') === null || request('activity') === '')>Tümü</option>
                <option value="active" @selected(request('activity') === 'active')>Konu veya yanıtı olanlar</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Yasak</label>
            <select name="banned" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="" @selected(request('banned') === null || request('banned') === '')>Tümü</option>
                <option value="1" @selected(request('banned') === '1')>Yasaklı</option>
                <option value="0" @selected(request('banned') === '0')>Serbest</option>
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white dark:bg-slate-100 dark:text-slate-900">Filtrele</button>
    </form>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-100">{{ session('error') }}</div>
    @endif

    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">Yalnızca <strong>yönetici olmayan</strong> site hesapları listelenir. Konu/yanıt sayıları topluluktan gelir. Üye silindiğinde bağlı konu ve yanıtlar da silinir.</p>

    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Üye</th>
                    <th class="px-4 py-3">Kayıt</th>
                    <th class="px-4 py-3 text-center">Konular</th>
                    <th class="px-4 py-3 text-center">Yanıtlar</th>
                    <th class="px-4 py-3">Durum</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @forelse ($members as $member)
                    <tr class="admin-table-row">
                        <td class="px-4 py-2">
                            <div class="admin-td-strong">{{ $member->name }}</div>
                            <div class="font-mono text-xs text-slate-500">{{ $member->email }}</div>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-600 dark:text-slate-400">{{ $member->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2 text-center font-mono">{{ $member->community_topics_count }}</td>
                        <td class="px-4 py-2 text-center font-mono">{{ $member->community_posts_count }}</td>
                        <td class="px-4 py-2">
                            @if ($member->community_banned_at)
                                <span class="rounded-full bg-rose-500/15 px-2 py-0.5 text-xs font-semibold text-rose-800 dark:text-rose-200">Yasaklı</span>
                            @else
                                <span class="text-xs text-slate-500">Aktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.community.members.edit', $member) }}" class="admin-link-emerald">Detay</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $members->links() }}</div>
</x-admin.layout>
