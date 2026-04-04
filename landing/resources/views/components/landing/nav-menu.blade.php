@props([
    'items',
    'linkClass' => 'hv-muted-nav',
])
@foreach ($items as $item)
    <a
        href="{{ $item->resolvedHref() }}"
        class="{{ $linkClass }}"
        @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
    >{{ $item->label }}</a>
@endforeach
