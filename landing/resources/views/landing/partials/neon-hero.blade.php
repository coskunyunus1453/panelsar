<section class="hv-neon-hero relative pt-8 sm:pt-12 lg:pt-16">
    <div class="hv-container">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
            <div class="order-2 space-y-6 lg:order-1">
                @if (($landingNeonTop['badge'] ?? '') !== '')
                    <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide hv-neon-badge">
                        <span class="h-1.5 w-1.5 rounded-full hv-neon-dot"></span>
                        {{ $landingNeonTop['badge'] }}
                    </div>
                @endif
                <h1 class="text-3xl font-semibold leading-tight tracking-tight text-slate-900 sm:text-4xl lg:text-[2.5rem] dark:text-slate-50">
                    {{ $landingNeonTop['title'] }}
                </h1>
                <p class="max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg dark:text-slate-400">
                    {{ $landingNeonTop['lead'] }}
                </p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ url('/panel') }}" class="hv-neon-btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold">
                        {{ $landingNeonTop['cta_primary'] }}
                        <span class="text-xs opacity-90">→</span>
                    </a>
                    <a href="{{ route('site.setup') }}" class="hv-neon-btn-ghost inline-flex items-center justify-center rounded-xl border px-6 py-3 text-sm font-semibold">
                        {{ $landingNeonTop['cta_secondary'] }}
                    </a>
                </div>
            </div>

            <div class="order-1 lg:order-2">
                <div class="hv-neon-art relative mx-auto aspect-[4/3] w-full max-w-lg overflow-hidden rounded-2xl border p-4 sm:p-6 lg:max-w-none">
                    <svg class="hv-neon-hero-svg h-full w-full opacity-90 dark:opacity-100" viewBox="0 0 400 300" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <defs>
                            <linearGradient id="hvNeonG1" x1="0" y1="0" x2="400" y2="300" gradientUnits="userSpaceOnUse">
                                <stop stop-color="currentColor" stop-opacity="0.35"/>
                                <stop offset="1" stop-color="currentColor" stop-opacity="0.05"/>
                            </linearGradient>
                            <linearGradient id="hvNeonG2" x1="400" y1="0" x2="0" y2="300" gradientUnits="userSpaceOnUse">
                                <stop stop-color="currentColor" stop-opacity="0.2"/>
                                <stop offset="1" stop-color="currentColor" stop-opacity="0"/>
                            </linearGradient>
                            <filter id="hvNeonGlow" x="-20%" y="-20%" width="140%" height="140%">
                                <feGaussianBlur stdDeviation="4" result="b"/>
                                <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
                            </filter>
                        </defs>
                        <rect width="400" height="300" rx="24" fill="url(#hvNeonG1)"/>
                        <path d="M40 220 L120 80 L200 180 L280 60 L360 200" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" filter="url(#hvNeonGlow)" opacity="0.85"/>
                        <circle cx="120" cy="80" r="8" fill="currentColor" filter="url(#hvNeonGlow)"/>
                        <circle cx="200" cy="180" r="8" fill="currentColor" filter="url(#hvNeonGlow)"/>
                        <circle cx="280" cy="60" r="8" fill="currentColor" filter="url(#hvNeonGlow)"/>
                        <rect x="48" y="48" width="120" height="72" rx="12" stroke="currentColor" stroke-width="1.5" opacity="0.5"/>
                        <rect x="232" y="120" width="120" height="72" rx="12" stroke="currentColor" stroke-width="1.5" opacity="0.45"/>
                        <path d="M60 260h280" stroke="currentColor" stroke-width="1" stroke-dasharray="6 10" opacity="0.35"/>
                        <path d="M320 40v220" stroke="currentColor" stroke-width="1" stroke-dasharray="6 10" opacity="0.25"/>
                        <rect width="400" height="300" rx="24" fill="url(#hvNeonG2)"/>
                    </svg>
                    <div class="pointer-events-none absolute inset-0 rounded-2xl ring-1 ring-inset hv-neon-art-ring"></div>
                </div>
            </div>
        </div>
    </div>
</section>
