@php
    use Illuminate\Support\Str;
@endphp
<x-admin.layout title="Topluluk üyesi — {{ $member->name }}">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.community.members.index') }}" class="text-sm font-medium text-orange-600 hover:underline dark:text-orange-400">← Üye listesi</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-100">{{ session('error') }}</div>
    @endif

    @if ($member->community_banned_at)
        <div class="mb-6 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100">
            <strong>Topluluk yasağı aktif.</strong>
            @if ($member->community_ban_reason)
                <p class="mt-1">{{ $member->community_ban_reason }}</p>
            @endif
            <p class="mt-1 text-xs opacity-80">{{ $member->community_banned_at?->format('Y-m-d H:i') }}</p>
        </div>
    @endif

    @if ($member->community_shadowbanned_at)
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
            <strong>Gölge yasak aktif.</strong>
            <p class="mt-1">Üye hesabını fark etmez; yeni konu ve yanıtları moderasyon kuyruğuna düşer.</p>
            <p class="mt-1 text-xs opacity-80">{{ $member->community_shadowbanned_at?->format('Y-m-d H:i') }}</p>
        </div>
    @endif

    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/60">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Konular</div>
            <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $member->community_topics_count }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/60">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Yanıtlar</div>
            <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $member->community_posts_count }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/60">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Konu görüntülenme</div>
            <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $topicViewSum }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/60">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Son konu aktivitesi</div>
            <div class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $lastTopicActivity ? \Illuminate\Support\Carbon::parse((string) $lastTopicActivity)->format('Y-m-d H:i') : '—' }}</div>
        </div>
    </div>

    <form method="post" action="{{ route('admin.community.members.update', $member) }}" class="mb-10 max-w-2xl space-y-4">
        @csrf
        @method('PUT')
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-50">Profil</h2>
        <div>
            <label class="block text-sm font-medium">Ad</label>
            <input type="text" name="name" value="{{ old('name', $member->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
            @error('name')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">E-posta</label>
            <input type="email" name="email" value="{{ old('email', $member->email) }}" required autocomplete="off" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-900" />
            @error('email')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Yönetici notu (iç kullanım)</label>
            <textarea name="community_admin_notes" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">{{ old('community_admin_notes', $member->community_admin_notes) }}</textarea>
            @error('community_admin_notes')
                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-900">Kaydet</button>
    </form>

    <div class="mb-10 max-w-2xl space-y-6">
        <div>
            <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-50">Gölge yasak</h2>
            @if (! $member->community_shadowbanned_at)
                <form method="post" action="{{ route('admin.community.members.shadowban', $member) }}" class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-4 dark:border-amber-900/40 dark:bg-amber-950/20" onsubmit="return confirm('Bu üyenin yeni içeriği gizlice moderasyona düşsün mü?');">
                    @csrf
                    <p class="text-sm text-slate-700 dark:text-slate-300">Üye normal görünür; yalnızca <strong>yeni</strong> konu ve yanıtlar onay bekler. Tam yasak için aşağıdaki “Yasakla”yı kullanın.</p>
                    <button type="submit" class="mt-3 rounded-xl bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Gölge yasak uygula</button>
                </form>
            @else
                <form method="post" action="{{ route('admin.community.members.unshadowban', $member) }}" class="inline" onsubmit="return confirm('Gölge yasağı kaldırılsın mı?');">
                    @csrf
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Gölge yasağı kaldır</button>
                </form>
            @endif
        </div>
        <div>
        <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-50">Topluluk yasağı</h2>
        @if (! $member->community_banned_at)
            <form method="post" action="{{ route('admin.community.members.ban', $member) }}" class="rounded-2xl border border-rose-200/80 bg-rose-50/40 p-4 dark:border-rose-900/40 dark:bg-rose-950/20" onsubmit="return confirm('Bu üye toplulukta yazamayacak. Emin misiniz?');">
                @csrf
                <p class="text-sm text-slate-700 dark:text-slate-300">Toplulukta <strong>yeni konu ve yanıt</strong> göndermesini engeller. Okuma açık kalır.</p>
                <div class="mt-3">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Gerekçe (isteğe bağlı)</label>
                    <textarea name="reason" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">{{ old('reason') }}</textarea>
                </div>
                <button type="submit" class="mt-3 rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Yasakla</button>
            </form>
        @else
            <form method="post" action="{{ route('admin.community.members.unban', $member) }}" class="inline" onsubmit="return confirm('Yasağı kaldırmak istiyor musunuz?');">
                @csrf
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yasağı kaldır</button>
            </form>
        @endif
        </div>
    </div>

    <div class="admin-table-wrap mb-10">
        <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-slate-50">Son konular</h2>
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Başlık</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @forelse ($recentTopics as $t)
                    <tr class="admin-table-row">
                        <td class="admin-td-strong px-4 py-2">{{ Str::limit($t->title, 80) }}</td>
                        <td class="px-4 py-2">{{ $t->category?->name }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.community.topics.edit', $t) }}" class="admin-link-emerald">Moderasyon</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">Konu yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="max-w-2xl rounded-2xl border border-rose-300/80 bg-rose-50/30 p-4 dark:border-rose-900/50 dark:bg-rose-950/20">
        <h2 class="text-lg font-semibold text-rose-900 dark:text-rose-100">Hesabı sil</h2>
        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">Kullanıcı ve bu kullanıcıya bağlı tüm topluluk konuları ile yanıtları kalıcı olarak silinir. İşlem geri alınamaz.</p>
        <form method="post" action="{{ route('admin.community.members.destroy', $member) }}" class="mt-3" onsubmit="return confirm('Üye ve topluluk içerikleri silinsin mi? Bu işlem geri alınamaz.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-xl border border-rose-600 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:bg-slate-900 dark:text-rose-300">Üyeyi sil</button>
        </form>
    </div>
</x-admin.layout>
