<footer class="hv-neon-footer relative z-10 mt-auto border-t border-slate-200/80 bg-white/60 py-10 backdrop-blur-xl dark:border-slate-800/60 dark:bg-slate-950/80">
    <div class="hv-container">
        <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-sm space-y-2">
                <div class="flex items-center gap-2">
                    <x-landing.brand-logo variant="neon-footer" />
                    <span class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ landing_p('brand.name') }}</span>
                </div>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ landing_p('brand.subtitle') }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-500">
                    © {{ date('Y') }} {{ landing_p('brand.name') }}. {{ landing_p('footer.rights') }}
                    @if ($hvNeonFootNote = \App\Services\LandingAppearance::footerExtraNote())
                        <span class="mt-1 block text-slate-500">{{ $hvNeonFootNote }}</span>
                    @endif
                </p>
                <x-landing.footer-extras />
            </div>
            <div class="grid grid-cols-2 gap-8 sm:grid-cols-3">
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Site</p>
                    <ul class="space-y-2 text-sm font-medium">
                        @foreach ($landingFooterNav as $item)
                            <li>
                                <a href="{{ $item->resolvedHref() }}" class="hv-neon-footer-link"
                                   @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                                >{{ $item->label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Kaynaklar</p>
                    <ul class="space-y-2 text-sm font-medium">
                        <li><a href="{{ route('docs.index') }}" class="hv-neon-footer-link">{{ landing_p('nav.docs') }}</a></li>
                        <li><a href="{{ route('blog.index') }}" class="hv-neon-footer-link">{{ landing_p('nav.blog') }}</a></li>
                        <li><a href="{{ url('/panel') }}" class="hv-neon-footer-link">{{ landing_p('nav.panel') }}</a></li>
                    </ul>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-500">Durum</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Material · neon vurgu · gece modu</p>
                </div>
            </div>
        </div>
    </div>
</footer>
