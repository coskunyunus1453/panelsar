@props([
    'name',
    'value' => '',
    'id' => null,
    'placeholder' => '',
])
@php
    $fieldId = $id ?? $name;
@endphp
<div
    data-admin-quill
    data-textarea-id="{{ $fieldId }}"
    @if ($placeholder !== '') data-placeholder="{{ $placeholder }}" @endif
    class="mt-1 overflow-hidden rounded-xl border border-slate-300 dark:border-slate-600"
>
    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        class="sr-only"
        tabindex="-1"
        aria-hidden="true"
    >{{ $value }}</textarea>
    <div
        data-quill-host
        class="admin-quill-host bg-white dark:bg-slate-900/80"
        style="min-height: 280px"
    ></div>
</div>
