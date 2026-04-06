@php
    $pageTitle = app()->getLocale() === 'tr' ? 'Ödeme iptal' : 'Payment cancelled';
@endphp

<x-site.layout :title="$pageTitle">
    <div class="hv-container max-w-xl py-16">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-50">{{ $pageTitle }}</h1>
        <p class="mt-4 text-slate-600 dark:text-slate-400">
            {{ app()->getLocale() === 'tr'
                ? 'Ödeme tamamlanmadı. İsterseniz tekrar deneyebilirsiniz.'
                : 'Checkout was not completed. You can try again when ready.' }}
        </p>
        <p class="mt-8">
            <a href="{{ route('site.pricing') }}" class="hv-link-quiet font-semibold">{{ app()->getLocale() === 'tr' ? 'Fiyatlandırma' : 'Pricing' }}</a>
        </p>
    </div>
</x-site.layout>
