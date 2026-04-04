<aside x-data="{ open: false }"
       x-on:hv-toggle-drawer.window="open = !open"
       x-on:resize.window="if (window.innerWidth >= 768) open = false"
       class="fixed inset-0 z-30 md:hidden"
       x-cloak
       x-show="open"
       x-transition.opacity.duration.200>
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm dark:bg-black/60" @click="open = false"></div>
    <div class="hv-neon-drawer-panel absolute inset-y-0 right-0 flex w-full max-w-xs flex-col border-l shadow-2xl"
         x-show="open"
         x-transition.duration.220.origin-right>
        <div class="flex h-14 items-center justify-between border-b px-4">
            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ landing_p('brand.name') }}</span>
            <button type="button" @click="open = false" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border text-slate-700 dark:text-slate-200">
                <span class="sr-only">{{ landing_p('nav.close') }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M6 6l12 12M18 6L6 18" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($landingHeaderNav as $drawerItem)
                <a href="{{ $drawerItem->resolvedHref() }}"
                   class="hv-neon-drawer-link block rounded-xl px-3 py-2.5 text-sm font-medium"
                   @if ($drawerItem->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                >{{ $drawerItem->label }}</a>
            @endforeach
        </nav>
    </div>
</aside>
