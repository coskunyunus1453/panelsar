@php
    $hvContact = \App\Services\LandingAppearance::contactEmail();
    $hvTw = \App\Services\LandingAppearance::socialTwitterUrl();
    $hvGh = \App\Services\LandingAppearance::socialGithubUrl();
    $hvLi = \App\Services\LandingAppearance::socialLinkedinUrl();
@endphp
@if ($hvContact || $hvTw || $hvGh || $hvLi)
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-medium">
        @if ($hvContact)
            <a href="mailto:{{ $hvContact }}" class="hv-muted-nav hover:text-[rgb(var(--hv-brand-600)/1)] dark:hover:text-[rgb(var(--hv-brand-400)/1)]">{{ $hvContact }}</a>
        @endif
        @if ($hvTw)
            <a href="{{ $hvTw }}" class="hv-muted-nav hover:text-[rgb(var(--hv-brand-600)/1)] dark:hover:text-[rgb(var(--hv-brand-400)/1)]" target="_blank" rel="noopener noreferrer">X / Twitter</a>
        @endif
        @if ($hvGh)
            <a href="{{ $hvGh }}" class="hv-muted-nav hover:text-[rgb(var(--hv-brand-600)/1)] dark:hover:text-[rgb(var(--hv-brand-400)/1)]" target="_blank" rel="noopener noreferrer">GitHub</a>
        @endif
        @if ($hvLi)
            <a href="{{ $hvLi }}" class="hv-muted-nav hover:text-[rgb(var(--hv-brand-600)/1)] dark:hover:text-[rgb(var(--hv-brand-400)/1)]" target="_blank" rel="noopener noreferrer">LinkedIn</a>
        @endif
    </div>
@endif
