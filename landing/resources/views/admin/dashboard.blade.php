@php
    use Illuminate\Support\Str;
    $k = $kpis;
    $pendingTotal = ($k['community_pending_topics'] ?? 0) + ($k['community_pending_posts'] ?? 0);
@endphp
<x-admin.layout title="Özet — Kontrol paneli">
    <div class="space-y-8">
        {{-- Üst karşılama --}}
        <div class="relative overflow-hidden rounded-3xl border border-slate-200/90 bg-gradient-to-br from-white via-orange-50/40 to-amber-50/30 p-6 shadow-sm dark:border-slate-800/80 dark:from-slate-950 dark:via-slate-900/90 dark:to-orange-950/20 sm:p-8">
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-orange-400/20 blur-3xl dark:bg-orange-500/10"></div>
            <div class="pointer-events-none absolute bottom-0 left-1/3 h-32 w-64 rounded-full bg-amber-300/15 blur-3xl dark:bg-amber-600/10"></div>
            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-orange-700/90 dark:text-orange-300/90">Hostvim yönetim</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                        Merhaba, {{ Str::limit(auth()->user()->name, 32) }}
                    </h1>
                    <p class="mt-2 max-w-xl text-sm text-slate-600 dark:text-slate-400">
                        Landing, blog, dokümanlar, topluluk ve lisanslar tek ekranda. Aşağıda canlı özetler ve son hareketler var.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('landing.home') }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Siteyi aç
                    </a>
                    @if ($has_community && $pendingTotal > 0)
                        <a href="{{ route('admin.community.moderation.index') }}"
                           class="inline-flex items-center gap-2 rounded-2xl border-2 border-amber-500/80 bg-amber-500/10 px-4 py-2.5 text-sm font-bold text-amber-900 ring-1 ring-amber-500/30 dark:text-amber-100">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                            </span>
                            Moderasyon ({{ $pendingTotal }})
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- KPI kartları --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="group admin-card relative overflow-hidden border-slate-200/80 p-5 transition hover:border-orange-300/50 hover:shadow-md dark:border-slate-800 dark:hover:border-orange-500/30">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="admin-kicker text-orange-600/90 dark:text-orange-400/90">Blog</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($k['blog_published']) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">yayında · {{ $k['blog_drafts'] }} taslak</p>
                    </div>
                    <div class="rounded-2xl bg-orange-500/10 p-3 text-orange-600 dark:text-orange-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                    </div>
                </div>
                <a href="{{ route('admin.blog-posts.index') }}" class="admin-link-emerald mt-4 inline-flex text-xs font-semibold">Yazıları yönet →</a>
            </div>

            <div class="group admin-card relative overflow-hidden border-slate-200/80 p-5 transition hover:border-sky-300/50 hover:shadow-md dark:border-slate-800 dark:hover:border-sky-500/30">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="admin-kicker text-sky-600/90 dark:text-sky-400/90">Dokümanlar</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($k['docs_published']) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">yayınlanan sayfa</p>
                    </div>
                    <div class="rounded-2xl bg-sky-500/10 p-3 text-sky-600 dark:text-sky-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                </div>
                <a href="{{ route('admin.doc-pages.index') }}" class="admin-link-emerald mt-4 inline-flex text-xs font-semibold">Dokümanlar →</a>
            </div>

            <div class="group admin-card relative overflow-hidden border-slate-200/80 p-5 transition hover:border-violet-300/50 hover:shadow-md dark:border-slate-800 dark:hover:border-violet-500/30">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="admin-kicker text-violet-600/90 dark:text-violet-400/90">Site sayfaları</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($k['site_pages']) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">yayında · menü {{ $k['nav_items'] }} öğe</p>
                    </div>
                    <div class="rounded-2xl bg-violet-500/10 p-3 text-violet-600 dark:text-violet-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                    <a href="{{ route('admin.site-pages.index') }}" class="admin-link-emerald font-semibold">Sayfalar</a>
                    <span class="text-slate-300 dark:text-slate-600">·</span>
                    <a href="{{ route('admin.nav-menu.index') }}" class="font-semibold text-slate-600 hover:text-orange-600 dark:text-slate-400 dark:hover:text-orange-400">Menü</a>
                </div>
            </div>

            <div class="group admin-card relative overflow-hidden border-slate-200/80 p-5 transition hover:border-emerald-300/50 hover:shadow-md dark:border-slate-800 dark:hover:border-emerald-500/30">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="admin-kicker text-emerald-600/90 dark:text-emerald-400/90">Planlar</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($k['plans']) }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">fiyat / paket kaydı</p>
                    </div>
                    <div class="rounded-2xl bg-emerald-500/10 p-3 text-emerald-600 dark:text-emerald-400">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 18.75v-9.75m0 9.75c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75"/></svg>
                    </div>
                </div>
                <a href="{{ route('admin.plans.index') }}" class="admin-link-emerald mt-4 inline-flex text-xs font-semibold">Planları düzenle →</a>
            </div>
        </div>

        @if ($has_community || $has_saas)
            <div class="grid gap-4 lg:grid-cols-2">
                @if ($has_community)
                    <div class="admin-card border-slate-200/80 p-6 dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="admin-kicker">Topluluk (forum)</p>
                                <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Özet</h2>
                            </div>
                            <a href="{{ route('admin.community.topics.index') }}" class="rounded-xl bg-orange-500/10 px-3 py-1.5 text-xs font-semibold text-orange-800 dark:text-orange-200">Konular</a>
                        </div>
                        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($k['community_topics']) }}</p>
                                <p class="text-xs text-slate-500">konu</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($k['community_posts']) }}</p>
                                <p class="text-xs text-slate-500">yanıt</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($k['community_members']) }}</p>
                                <p class="text-xs text-slate-500">aktif üye</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/50">
                                <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($k['community_views_sum']) }}</p>
                                <p class="text-xs text-slate-500">görüntülenme</p>
                            </div>
                        </div>
                        @if ($pendingTotal > 0)
                            <div class="mt-4 flex flex-wrap items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                <span class="font-semibold">{{ $k['community_pending_topics'] }} konu</span>
                                <span class="text-amber-700/70 dark:text-amber-300/70">·</span>
                                <span class="font-semibold">{{ $k['community_pending_posts'] }} yanıt</span>
                                <span class="text-xs opacity-80">onay bekliyor</span>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($has_saas)
                    <div class="admin-card border-slate-200/80 p-6 dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="admin-kicker">SaaS / lisans</p>
                                <h2 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Özet</h2>
                            </div>
                            <a href="{{ route('admin.saas.dashboard') }}" class="rounded-xl bg-indigo-500/10 px-3 py-1.5 text-xs font-semibold text-indigo-800 dark:text-indigo-200">Lisans paneli</a>
                        </div>
                        <div class="mt-6 grid grid-cols-2 gap-4">
                            <div class="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-indigo-50/80 to-white p-5 dark:border-slate-700 dark:from-indigo-950/40 dark:to-slate-900/40">
                                <p class="text-3xl font-bold text-indigo-900 dark:text-indigo-100">{{ number_format($k['saas_customers']) }}</p>
                                <p class="text-sm text-indigo-800/80 dark:text-indigo-200/80">müşteri</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-emerald-50/80 to-white p-5 dark:border-slate-700 dark:from-emerald-950/40 dark:to-slate-900/40">
                                <p class="text-3xl font-bold text-emerald-900 dark:text-emerald-100">{{ number_format($k['saas_licenses_active']) }}</p>
                                <p class="text-sm text-emerald-800/80 dark:text-emerald-200/80">aktif lisans</p>
                            </div>
                        </div>
                        <a href="{{ route('admin.saas.licenses.index') }}" class="admin-link-emerald mt-4 inline-flex text-xs font-semibold">Tüm lisanslar →</a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Grafikler --}}
        @if (($has_community && count($community_series) > 0) || count($blog_series) > 0)
        <div class="grid gap-6 lg:grid-cols-2">
            @if ($has_community && count($community_series) > 0)
                <div class="admin-card border-slate-200/80 p-6 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Yeni konular (14 gün)</h2>
                        <span class="text-xs text-slate-500">günlük</span>
                    </div>
                    <div class="mt-6 flex h-44 items-end justify-between gap-1 sm:gap-1.5">
                        @foreach ($community_series as $bar)
                            <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-1" title="{{ $bar['count'] }} konu">
                                <span class="text-[10px] font-medium tabular-nums text-slate-500 dark:text-slate-400">{{ $bar['count'] > 0 ? $bar['count'] : '' }}</span>
                                <div class="flex h-28 w-full max-w-9 items-end justify-center">
                                    <div
                                        class="w-[72%] rounded-t-md bg-gradient-to-t from-orange-600 to-orange-400/90 shadow-sm dark:from-orange-500 dark:to-orange-300/80"
                                        style="height: {{ $bar['height_px'] }}px; min-height: 4px"
                                    ></div>
                                </div>
                                <span class="truncate text-[9px] text-slate-400 sm:text-[10px]">{{ $bar['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (count($blog_series) > 0)
                <div class="admin-card border-slate-200/80 p-6 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Blog — yeni yazı (14 gün)</h2>
                        <span class="text-xs text-slate-500">oluşturulma</span>
                    </div>
                    <div class="mt-6 flex h-44 items-end justify-between gap-1 sm:gap-1.5">
                        @foreach ($blog_series as $bar)
                            <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-1" title="{{ $bar['count'] }} yazı">
                                <span class="text-[10px] font-medium tabular-nums text-slate-500 dark:text-slate-400">{{ $bar['count'] > 0 ? $bar['count'] : '' }}</span>
                                <div class="flex h-28 w-full max-w-9 items-end justify-center">
                                    <div
                                        class="w-[72%] rounded-t-md bg-gradient-to-t from-sky-600 to-sky-400/90 shadow-sm dark:from-sky-500 dark:to-sky-300/80"
                                        style="height: {{ $bar['height_px'] }}px; min-height: 4px"
                                    ></div>
                                </div>
                                <span class="truncate text-[9px] text-slate-400 sm:text-[10px]">{{ $bar['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Hızlı erişim --}}
            <div class="admin-card border-slate-200/80 p-6 dark:border-slate-800 xl:col-span-1">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Hızlı erişim</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sık kullanılan yönetim sayfaları</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('admin.theme-settings.edit') }}" class="flex items-center justify-between rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-slate-900/50"><span>Tema &amp; görünüm</span><span class="text-slate-400">→</span></a></li>
                    <li><a href="{{ route('admin.public-home-content.edit') }}" class="flex items-center justify-between rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-slate-900/50"><span>Ana sayfa içeriği</span><span class="text-slate-400">→</span></a></li>
                    <li><a href="{{ route('admin.locale-settings.edit') }}" class="flex items-center justify-between rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-slate-900/50"><span>Dil ayarları</span><span class="text-slate-400">→</span></a></li>
                    <li><a href="{{ route('admin.billing-settings.edit') }}" class="flex items-center justify-between rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-slate-900/50"><span>Ödeme (Stripe / PayTR)</span><span class="text-slate-400">→</span></a></li>
                    @if ($has_community)
                        <li><a href="{{ route('admin.community.settings.edit') }}" class="flex items-center justify-between rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-slate-900/50"><span>Topluluk SEO</span><span class="text-slate-400">→</span></a></li>
                        <li><a href="{{ route('admin.community.categories.index') }}" class="flex items-center justify-between rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 hover:bg-slate-50 dark:hover:border-slate-700 dark:hover:bg-slate-900/50"><span>Topluluk kategorileri</span><span class="text-slate-400">→</span></a></li>
                    @endif
                </ul>
            </div>

            {{-- Son konular --}}
            @if ($has_community && $recent_topics->isNotEmpty())
                <div class="admin-card border-slate-200/80 p-0 overflow-hidden dark:border-slate-800 xl:col-span-1">
                    <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Son konular</h2>
                        <p class="text-xs text-slate-500">Son aktiviteye göre</p>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach ($recent_topics as $topic)
                            <li>
                                <a href="{{ route('admin.community.topics.edit', $topic) }}" class="flex flex-col gap-0.5 px-5 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ Str::limit($topic->title, 52) }}</span>
                                    <span class="text-xs text-slate-500">{{ $topic->category?->name ?? '—' }} · {{ $topic->last_activity_at?->diffForHumans() ?? '—' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Son blog --}}
            @if ($recent_blog->isNotEmpty())
                <div class="admin-card border-slate-200/80 p-0 overflow-hidden dark:border-slate-800 xl:col-span-1">
                    <div class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Son blog düzenlemeleri</h2>
                        <p class="text-xs text-slate-500">Güncellenme tarihi</p>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-800/80">
                        @foreach ($recent_blog as $post)
                            <li>
                                <a href="{{ route('admin.blog-posts.edit', $post) }}" class="flex flex-col gap-0.5 px-5 py-3 text-sm hover:bg-slate-50 dark:hover:bg-slate-900/40">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ Str::limit($post->title, 52) }}</span>
                                    <span class="text-xs text-slate-500">{{ strtoupper($post->locale ?? '') }} · {{ $post->updated_at?->diffForHumans() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-admin.layout>
