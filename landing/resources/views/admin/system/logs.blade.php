@php
    $total = count($entries);
    $maxLevel = max(1, $levels['error'] + $levels['warning'] + $levels['info'] + $levels['other']);
    $refreshSeconds = (int) request('refresh', 0);
@endphp
<x-admin.layout title="Sistem Logları">
    <div class="space-y-6">
        <section class="relative overflow-hidden rounded-3xl border border-slate-200/90 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm dark:border-slate-800 dark:from-slate-950 dark:to-slate-900/60">
            <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-orange-400/15 blur-3xl"></div>
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="admin-kicker text-orange-700/90 dark:text-orange-300/90">Operasyon merkezi</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Sistem Logları</h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-400">
                        Nginx, Apache, OpenLiteSpeed, PHP, Laravel ve sistem loglarını tek ekranda izle. Satırlar performans için kuyruktan sınırlı okunur.
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 text-right dark:border-slate-700 dark:bg-slate-900/70">
                    <div class="text-xs text-slate-500">Listeleme</div>
                    <div class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($total) }}</div>
                    <div class="text-xs text-slate-500">satır</div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="admin-card p-4">
                <div class="admin-kicker">Hata</div>
                <div class="mt-1 text-2xl font-bold text-rose-700 dark:text-rose-300">{{ $levels['error'] }}</div>
            </div>
            <div class="admin-card p-4">
                <div class="admin-kicker">Uyarı</div>
                <div class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $levels['warning'] }}</div>
            </div>
            <div class="admin-card p-4">
                <div class="admin-kicker">Bilgi</div>
                <div class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $levels['info'] }}</div>
            </div>
            <div class="admin-card p-4">
                <div class="admin-kicker">Diğer</div>
                <div class="mt-1 text-2xl font-bold text-slate-700 dark:text-slate-200">{{ $levels['other'] }}</div>
            </div>
        </section>

        <section class="admin-card p-5">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Seviye dağılımı</h2>
            <div class="mt-4 grid grid-cols-4 gap-3">
                @foreach ([
                    'error' => 'rgb(244 63 94 / 0.9)',
                    'warning' => 'rgb(245 158 11 / 0.9)',
                    'info' => 'rgb(16 185 129 / 0.9)',
                    'other' => 'rgb(100 116 139 / 0.9)',
                ] as $lvl => $barColor)
                    @php $h = max(8, (int) round(($levels[$lvl] / $maxLevel) * 120)); @endphp
                    <div class="rounded-xl border border-slate-200 p-3 text-center dark:border-slate-700">
                        <div class="mx-auto flex h-32 items-end justify-center">
                            <div class="w-8 rounded-t-md" style="height: {{ $h }}px; background: {{ $barColor }};"></div>
                        </div>
                        <div class="mt-2 text-xs font-medium uppercase text-slate-500">{{ $lvl }}</div>
                        <div class="text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $levels[$lvl] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="admin-card p-5">
            <form method="get" class="grid gap-3 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <label class="admin-label">Arama</label>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Mesaj, kaynak, site..." class="admin-field mt-1">
                </div>
                <div class="lg:col-span-3">
                    <label class="admin-label">Seviye</label>
                    <select name="level" class="admin-field mt-1">
                        <option value="all" @selected($active_level === 'all')>Tümü</option>
                        <option value="error" @selected($active_level === 'error')>Error</option>
                        <option value="warning" @selected($active_level === 'warning')>Warning</option>
                        <option value="info" @selected($active_level === 'info')>Info</option>
                        <option value="other" @selected($active_level === 'other')>Other</option>
                    </select>
                </div>
                <div class="lg:col-span-3">
                    <label class="admin-label">Web Sitesi</label>
                    <select name="site" class="admin-field mt-1">
                        <option value="all" @selected($active_site === 'all')>Tümü</option>
                        @foreach ($sites as $s)
                            <option value="{{ $s }}" @selected($active_site === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2 flex items-end gap-2">
                    <button type="submit" class="admin-btn-emerald w-full justify-center">Uygula</button>
                </div>
                <div class="lg:col-span-4">
                    <label class="mt-1 inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="today" value="1" class="admin-checkbox" @checked($active_today)>
                        Sadece bugünkü kayıtlar
                    </label>
                </div>
                <div class="lg:col-span-4">
                    <label class="admin-label">Otomatik yenileme</label>
                    <select name="refresh" class="admin-field mt-1">
                        <option value="0" @selected($refreshSeconds === 0)>Kapalı</option>
                        <option value="15" @selected($refreshSeconds === 15)>15 saniye</option>
                        <option value="30" @selected($refreshSeconds === 30)>30 saniye</option>
                        <option value="60" @selected($refreshSeconds === 60)>60 saniye</option>
                    </select>
                </div>
                <div class="lg:col-span-4 flex items-end">
                    <a
                        href="{{ route('admin.system.logs.export', request()->query()) }}"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                        CSV indir
                    </a>
                </div>

                <div class="lg:col-span-12 mt-2 flex flex-wrap gap-2">
                    @foreach ($tabs as $tabKey => $tabItem)
                        <a
                            href="{{ route('admin.system.logs.index', array_merge(request()->except('page'), ['tab' => $tabKey])) }}"
                            class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $active_tab === $tabKey ? 'bg-orange-500/15 text-orange-800 ring-1 ring-orange-500/30 dark:text-orange-200' : 'border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900/70' }}"
                        >
                            {{ $tabItem['label'] }}
                            <span class="ml-1 tabular-nums opacity-80">({{ $tabItem['count'] }})</span>
                        </a>
                    @endforeach
                </div>
            </form>
        </section>

        <section class="admin-table-wrap mt-0">
            <table class="min-w-full text-left text-xs">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-4 py-3">Zaman</th>
                        <th class="px-4 py-3">Seviye</th>
                        <th class="px-4 py-3">Kaynak</th>
                        <th class="px-4 py-3">Site</th>
                        <th class="px-4 py-3">Mesaj</th>
                    </tr>
                </thead>
                <tbody class="admin-table-body">
                    @forelse ($entries as $e)
                        <tr class="admin-table-row align-top">
                            <td class="px-4 py-2 whitespace-nowrap text-slate-500">{{ $e['timestamp'] }}</td>
                            <td class="px-4 py-2">
                                @if ($e['level'] === 'error')
                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">error</span>
                                @elseif ($e['level'] === 'warning')
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">warning</span>
                                @elseif ($e['level'] === 'info')
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">info</span>
                                @else
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">other</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <div class="font-medium text-slate-800 dark:text-slate-100">{{ $e['source_label'] }}</div>
                                <div class="text-[11px] text-slate-500">{{ $e['file_name'] }}</div>
                            </td>
                            <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $e['site'] }}</td>
                            <td class="px-4 py-2">
                                <div class="max-w-[70ch] break-words font-mono text-[11px] leading-5 text-slate-700 dark:text-slate-200">{{ $e['message'] }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                Filtreye uygun log kaydı bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
    @if ($refreshSeconds > 0)
        <script>
            setTimeout(function () {
                window.location.reload();
            }, {{ max(5, $refreshSeconds) * 1000 }});
        </script>
    @endif
</x-admin.layout>
