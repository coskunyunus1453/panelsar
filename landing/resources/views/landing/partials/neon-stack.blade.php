<section class="hv-neon-stack relative mt-16 sm:mt-20 lg:mt-24">
    <div class="hv-container">
        <div class="mx-auto mb-10 max-w-2xl text-center">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl dark:text-slate-50">{{ $landingNeonStackSection['title'] }}</h2>
            <p class="mt-3 text-base text-slate-600 dark:text-slate-400">{{ $landingNeonStackSection['lead'] }}</p>
        </div>
        <div class="mx-auto max-w-3xl space-y-4">
            @foreach ($landingNeonStackItems as $item)
                <article class="hv-neon-stack-card flex gap-4 rounded-2xl border p-5 sm:gap-5 sm:p-6">
                    <div class="hv-neon-icon-ring flex h-11 w-11 shrink-0 items-center justify-center rounded-xl sm:h-12 sm:w-12">
                        <x-landing.feature-icon :name="$item['icon'] ?? 'layers'" class="!h-5 !w-5 sm:!h-6 sm:!w-6" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $item['title'] }}</h3>
                        <p class="text-base leading-relaxed text-slate-600 dark:text-slate-400">{{ $item['body'] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
