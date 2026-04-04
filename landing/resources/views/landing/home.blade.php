<x-layouts.landing>
    @unless (\App\Services\LandingAppearance::isNeonTheme())
    <section class="relative pt-10 sm:pt-14 lg:pt-20">
        <div class="hv-container">
            <div class="relative overflow-hidden rounded-3xl border border-slate-200/90 bg-white/90 hv-shadow-soft dark:border-slate-800/80 dark:bg-slate-900/60">
                <div class="hv-grid-fade"></div>

                <div class="relative flex flex-col gap-10 px-5 py-10 sm:px-8 sm:py-12 lg:flex-row lg:items-center lg:gap-12 lg:px-12 lg:py-14">
                    <div class="flex-1 space-y-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="hv-pill">
                                <span class="hv-accent-dot h-2 w-2 rounded-full shadow-[0_0_0_4px_rgb(var(--hv-brand-500)/0.25)] dark:shadow-[0_0_0_4px_rgb(var(--hv-brand-400)/0.2)]"></span>
                                {{ landing_p('home.hero_badge_engine') }}
                            </span>
                            <span class="hv-badge">
                                <span class="hv-accent-dot h-1.5 w-1.5 rounded-full opacity-90"></span>
                                {{ landing_p('home.hero_badge_model') }}
                            </span>
                        </div>

                        <div class="space-y-4">
                            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl lg:text-[2.65rem] lg:leading-tight dark:text-slate-50">
                                {!! str_replace(
                                    ':brand',
                                    '<span class="font-semibold hv-text-brand">'.e(landing_p('brand.name')).'</span>',
                                    e(landing_p('home.hero_title'))
                                ) !!}
                            </h1>
                            <p class="max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-400">
                                {{ landing_p('home.hero_lead') }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 pt-1 sm:flex-row">
                            <a href="{{ route('site.pricing') }}" class="hv-btn-primary gap-2 px-5 py-3 text-base">
                                {{ landing_p('home.hero_cta_primary') }}
                                <span class="text-sm opacity-90">→</span>
                            </a>
                            <a href="{{ route('site.setup') }}" class="hv-btn-secondary gap-2 px-5 py-3 text-base">
                                {{ landing_p('home.hero_cta_secondary') }}
                            </a>
                        </div>

                        <dl class="grid grid-cols-2 gap-4 pt-2 text-sm sm:flex sm:flex-wrap sm:gap-8 dark:text-slate-400">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-500">{{ landing_p('home.hero_stat_server') }}</dt>
                                <dd class="mt-1 font-medium text-slate-900 dark:text-slate-200">{{ landing_p('home.hero_stat_server_val') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ landing_p('home.hero_stat_security') }}</dt>
                                <dd class="mt-1 font-medium text-slate-900 dark:text-slate-200">{{ landing_p('home.hero_stat_security_val') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ landing_p('home.hero_stat_stack') }}</dt>
                                <dd class="mt-1 font-medium text-slate-900 dark:text-slate-200">{{ landing_p('home.hero_stat_stack_val') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ landing_p('home.hero_stat_model') }}</dt>
                                <dd class="mt-1 font-medium hv-text-brand-strong">{{ landing_p('home.hero_stat_model_val') }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="relative mx-auto w-full max-w-md space-y-5 lg:max-w-sm">
                        @if (! empty($landingHeroImageUrl))
                            <figure class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-lg dark:border-slate-800 dark:bg-slate-950/80">
                                <img src="{{ $landingHeroImageUrl }}" alt="{{ e($landingHeroImageAlt) }}" class="aspect-[4/3] w-full object-cover" loading="lazy">
                                @if (! empty($landingHeroImageCaption))
                                    <figcaption class="border-t border-slate-200/90 bg-slate-50/90 px-4 py-2 text-center text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-400">
                                        {{ $landingHeroImageCaption }}
                                    </figcaption>
                                @endif
                            </figure>
                        @endif
                        <div class="rounded-2xl border border-slate-200/90 bg-white/95 p-5 shadow-lg dark:border-slate-800 dark:bg-slate-950/80 dark:shadow-xl">
                            <div class="mb-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                <span class="flex items-center gap-1">
                                    <span class="hv-accent-dot h-2 w-2 rounded-full"></span>
                                    hostvim engine
                                </span>
                                <span>Realtime health</span>
                            </div>
                            <div class="space-y-3">
                                <div class="grid grid-cols-3 gap-3 text-xs">
                                    <div class="rounded-xl border border-slate-200/90 bg-slate-50 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/80">
                                        <div class="mb-1 text-[10px] text-slate-500">CPU</div>
                                        <div class="flex items-baseline gap-1">
                                            <span class="text-base font-semibold hv-text-brand">23%</span>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-slate-200/90 bg-slate-50 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/80">
                                        <div class="mb-1 text-[10px] text-slate-500">RAM</div>
                                        <div class="flex items-baseline gap-1 text-slate-800 dark:text-slate-200">
                                            <span class="text-base font-semibold">3.1GB</span>
                                            <span class="text-[10px] text-slate-500">/8GB</span>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-slate-200/90 bg-slate-50 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900/80">
                                        <div class="mb-1 text-[10px] text-slate-500">Disk</div>
                                        <div class="flex items-baseline gap-1">
                                            <span class="text-base font-semibold text-amber-600 dark:text-amber-300">68%</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between rounded-xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white px-3 py-3 dark:border-slate-800 dark:from-slate-900/90 dark:to-slate-950/90">
                                    <div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Aktif siteler</div>
                                        <div class="text-base font-semibold text-slate-900 dark:text-slate-100">12 domain</div>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px] font-semibold hv-border-brand hv-bg-brand-soft text-[rgb(var(--hv-brand-800)/1)] dark:text-[rgb(var(--hv-brand-200)/1)]">
                                        %99.97 uptime
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endunless

    @if (\App\Services\LandingAppearance::isNeonTheme())
        @include('landing.partials.neon-hero')
        @include('landing.partials.neon-stack')
        @include('landing.partials.neon-grid')
    @endif

    @unless (\App\Services\LandingAppearance::isNeonTheme())
    <section id="features" class="relative mt-16 sm:mt-20 lg:mt-24">
        <div class="hv-container">
            <div class="mb-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-3xl">
                    <div class="hv-section-eyebrow">{{ landing_p('home.features_badge') }}</div>
                    <h2 class="hv-section-title">{{ landing_p('home.features_title') }}</h2>
                    <p class="hv-section-lead">{{ landing_p('home.features_lead') }}</p>
                </div>
            </div>

            <div class="grid gap-5 text-base sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($landingFeatureCards ?? [] as $card)
                    <article class="hv-glass flex flex-col gap-3 rounded-2xl p-6">
                        <div class="hv-card-icon">
                            <x-landing.feature-icon :name="$card['icon'] ?? 'layers'" />
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $card['title'] }}</h3>
                        <p class="text-base leading-relaxed text-slate-600 dark:text-slate-400">{{ $card['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
    @endunless

    <section id="pricing" class="relative mt-20 lg:mt-24 @if (\App\Services\LandingAppearance::isNeonTheme()) hv-neon-page-section @endif">
        <div class="hv-container">
            <div class="mb-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-3xl">
                    <div class="hv-section-eyebrow">{{ landing_p('home.pricing_badge') }}</div>
                    <h2 class="hv-section-title">{{ landing_p('home.pricing_title') }}</h2>
                    <p class="hv-section-lead">{{ landing_p('home.pricing_lead') }}</p>
                </div>
                <div class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ landing_p('home.pricing_period') }}</div>
            </div>

            <div class="grid gap-5 text-base sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex flex-col rounded-2xl border border-slate-200/90 bg-white/90 p-6 dark:border-slate-800 dark:bg-slate-900/60">
                    <h3 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Freemium</h3>
                    <p class="mb-4 text-base text-slate-600 dark:text-slate-400">Tek sunucu için sınırlı ama yeterli özellikler.</p>
                    <div class="mb-4 flex items-baseline gap-1 text-slate-900 dark:text-slate-100">
                        <span class="text-3xl font-semibold">₺0</span>
                        <span class="text-sm text-slate-500">/ay</span>
                    </div>
                    <ul class="mb-6 flex-1 space-y-2 text-base text-slate-700 dark:text-slate-300">
                        <li>• 1 sunucu</li>
                        <li>• Temel site &amp; domain yönetimi</li>
                        <li>• Otomatik SSL</li>
                        <li>• Sınırlı log &amp; terminal erişimi</li>
                    </ul>
                    <button type="button" class="mt-auto inline-flex w-full items-center justify-center rounded-full border border-slate-300/90 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100">
                        Ücretsiz başla
                    </button>
                </div>

                <div class="hv-card-pro">
                    <div class="hv-card-pro-badge">
                        Önerilen
                    </div>
                    <h3 class="mb-1 pr-16 text-lg font-semibold text-slate-900 dark:text-slate-50">Pro Lisans</h3>
                    <p class="mb-4 text-base text-slate-600 dark:text-slate-300">Ajanslar ve yoğun trafik alan siteler için.</p>
                    <div class="mb-4 flex items-baseline gap-1 hv-text-brand">
                        <span class="text-3xl font-semibold">₺?</span>
                        <span class="text-sm">/ay · sunucu başına</span>
                    </div>
                    <ul class="mb-6 flex-1 space-y-2 text-base text-slate-800 dark:text-slate-200">
                        <li>• Sınırsız site &amp; domain</li>
                        <li>• Gelişmiş güvenlik profilleri</li>
                        <li>• Detaylı metrikler &amp; health checks</li>
                        <li>• Öncelikli destek</li>
                    </ul>
                    <button type="button" class="hv-btn-primary-sm mt-auto w-full py-2.5 text-sm font-semibold text-white">
                        Lansman fiyatı için iletişim
                    </button>
                </div>

                <div class="flex flex-col rounded-2xl border border-slate-200/90 bg-white/90 p-6 dark:border-slate-800 dark:bg-slate-900/60">
                    <h3 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Vendor / White-label</h3>
                    <p class="mb-4 text-base text-slate-600 dark:text-slate-400">Kendi markanla sunmak isteyen paneller / firmalar için.</p>
                    <p class="mb-6 flex-1 text-base text-slate-700 dark:text-slate-300">Özel fiyatlandırma, SLA ve roadmap iş birliği.</p>
                    <button type="button" class="mt-auto inline-flex w-full items-center justify-center rounded-full border border-slate-300/90 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100">
                        Kurumsal teklif iste
                    </button>
                </div>
            </div>
            <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-500">
                {{ landing_p('home.pricing_page_cta') }}
                <a href="{{ route('site.pricing') }}" class="hv-link-quiet">{{ landing_p('home.pricing_page_link') }}</a>.
            </p>
        </div>
    </section>

    <section id="docs" class="relative mt-20 lg:mt-24 @if (\App\Services\LandingAppearance::isNeonTheme()) hv-neon-page-section @endif">
        <div class="hv-container">
            <div class="grid items-start gap-10 lg:grid-cols-2">
                <div class="space-y-4">
                    <div class="hv-section-eyebrow">{{ landing_p('home.docs_badge') }}</div>
                    <h2 class="hv-section-title">{{ landing_p('home.docs_title') }}</h2>
                    <p class="hv-section-lead">
                        {{ landing_p('home.docs_lead') }}
                        <a href="{{ route('site.setup') }}" class="hv-link">{{ landing_p('nav.setup') }}</a>
                        ·
                        <a href="{{ route('docs.index') }}" class="hv-link">{{ landing_p('nav.docs') }}</a>
                    </p>

                    <div class="rounded-2xl border border-slate-200/90 bg-slate-50/80 p-5 font-mono text-sm text-slate-800 dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-200">
                        <div class="mb-2 flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-500">
                            <span>Kurulum komutu (örnek)</span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] dark:bg-slate-900">root@server</span>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-slate-200/90 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-900/80">
                            <span class="hv-code-accent">curl</span>
                            <span class="text-slate-600 dark:text-slate-400"> -fsSL https://get.hostvim.sh </span>
                            <span class="text-slate-400">|</span>
                            <span class="hv-code-accent"> bash</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 rounded-2xl border border-slate-200/90 bg-white/90 p-6 text-base text-slate-700 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ landing_p('home.docs_toc') }}</span>
                        <a href="{{ route('docs.index') }}" class="hv-badge border-[rgb(var(--hv-brand-500)/0.4)] text-[rgb(var(--hv-brand-700)/1)] dark:text-[rgb(var(--hv-brand-200)/1)]">{{ landing_p('home.docs_all') }}</a>
                    </div>
                    <ul class="space-y-2">
                        <li>• <a href="{{ route('docs.show', 'architecture') }}" class="hv-link font-medium">{{ landing_t('home.docs_link_architecture') }}</a></li>
                        <li>• <a href="{{ route('docs.show', 'getting-started') }}" class="hv-link font-medium">{{ landing_t('home.docs_link_getting_started') }}</a></li>
                        <li>• WordPress, Laravel ve static site senaryoları</li>
                        <li>• Güvenlik ve backup stratejileri</li>
                        <li>• Freemium &amp; lisanslama entegrasyonu</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="blog" class="relative mt-20 lg:mt-24 @if (\App\Services\LandingAppearance::isNeonTheme()) hv-neon-page-section @endif">
        <div class="hv-container">
            <div class="mb-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-3xl">
                    <div class="hv-section-eyebrow">{{ landing_p('home.blog_badge') }}</div>
                    <h2 class="hv-section-title">{{ landing_p('home.blog_title') }}</h2>
                    <p class="hv-section-lead">{{ landing_p('home.blog_lead') }}</p>
                </div>
                <a href="{{ route('blog.index') }}" class="hv-muted-nav hidden text-sm font-semibold sm:inline">{{ landing_p('home.blog_all') }}</a>
            </div>

            <div class="grid gap-5 text-base sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($latestPosts as $post)
                    <a href="{{ route('blog.show', $post->slug) }}" class="hv-blog-card group flex flex-col gap-2 p-6">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-500">{{ $post->published_at?->translatedFormat('d M Y') }}</p>
                        <h3 class="text-lg font-semibold text-slate-900 transition-colors group-hover:text-[rgb(var(--hv-brand-600)/1)] dark:text-slate-100 dark:group-hover:text-[rgb(var(--hv-brand-400)/1)]">{{ $post->title }}</h3>
                        <p class="line-clamp-3 text-base leading-relaxed text-slate-600 dark:text-slate-400">{{ $post->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($post->content), 140) }}</p>
                    </a>
                @empty
                    <article class="rounded-2xl border border-dashed border-slate-300/90 bg-slate-50/50 p-8 text-center text-base text-slate-500 sm:col-span-3 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-500">
                        {{ landing_p('home.blog_empty') }}
                    </article>
                @endforelse
            </div>
        </div>
    </section>

    <section id="faq" class="relative mb-20 mt-20 lg:mt-24 @if (\App\Services\LandingAppearance::isNeonTheme()) hv-neon-page-section @endif">
        <div class="hv-container">
            <div class="grid items-start gap-10 lg:grid-cols-2">
                <div class="max-w-3xl">
                    <div class="hv-section-eyebrow">{{ landing_p('home.faq_badge') }}</div>
                    <h2 class="hv-section-title">{{ landing_p('home.faq_title') }}</h2>
                    <p class="hv-section-lead">{{ landing_p('home.faq_lead') }}</p>
                </div>
                <div class="space-y-4 text-base text-slate-700 dark:text-slate-300">
                    <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                        <p class="mb-2 font-semibold text-slate-900 dark:text-slate-100">Freemium’da hangi kısıtlar var?</p>
                        <p class="leading-relaxed text-slate-600 dark:text-slate-400">Tek sunucu, sınırlı site sayısı ve temel metriklerle başlarsınız. Yükseltme yaptığınızda aynı panel devam eder.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                        <p class="mb-2 font-semibold text-slate-900 dark:text-slate-100">Lisans nasıl doğrulanacak?</p>
                        <p class="leading-relaxed text-slate-600 dark:text-slate-400">Panel tarafında lisans anahtarı oluşturulup, engine ile güvenli bir API üzerinden eşleşecek (entegrasyon bu projede kurgulanacak).</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                        <p class="mb-2 font-semibold text-slate-900 dark:text-slate-100">Mevcut panellerle birlikte kullanılabilir mi?</p>
                        <p class="leading-relaxed text-slate-600 dark:text-slate-400">Evet, farklı sunucularda farklı paneller çalıştırabilirsiniz. Hostvim kendi engine yapısı ile izole ilerler.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.landing>
