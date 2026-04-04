<section class="hv-neon-grid-section relative mt-16 sm:mt-20 lg:mt-24">
    <div class="hv-container">
        <div class="mx-auto mb-10 max-w-2xl text-center">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-50">{{ $landingNeonGridSection['title'] }}</h2>
            <p class="mt-3 text-base text-slate-600 dark:text-slate-400">{{ $landingNeonGridSection['lead'] }}</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
            @foreach ($landingNeonGridItems as $item)
                <article class="hv-neon-grid-card flex flex-col gap-3 rounded-2xl border p-5 sm:p-6">
                    <div class="hv-neon-icon-ring inline-flex h-10 w-10 items-center justify-center rounded-lg">
                        <x-landing.feature-icon :name="$item['icon'] ?? 'layers'" class="!h-5 !w-5" />
                    </div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $item['title'] }}</h3>
                    <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $item['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
