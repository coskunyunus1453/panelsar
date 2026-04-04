@props(['class' => ''])

<button type="button"
        {{ $attributes->merge(['class' => 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-300/90 bg-white/90 text-base shadow-sm hover:border-[rgb(var(--hv-brand-500)/0.45)] dark:border-slate-600 dark:bg-slate-900/90 dark:hover:border-[rgb(var(--hv-brand-400)/0.45)] '.$class]) }}
        onclick="(function(){ var d = document.documentElement.classList.toggle('dark'); localStorage.setItem('hv-theme', d ? 'dark' : 'light'); })()"
        title="{{ landing_t('nav.theme') }}"
        aria-label="{{ landing_t('nav.theme') }}">
    <span class="hidden dark:inline" aria-hidden="true">☀️</span>
    <span class="inline dark:hidden" aria-hidden="true">🌙</span>
</button>
