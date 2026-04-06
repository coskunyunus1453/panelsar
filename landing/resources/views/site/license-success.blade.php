@php
    $pageTitle = app()->getLocale() === 'tr' ? 'Ödeme tamamlandı' : 'Payment complete';
@endphp

<x-site.layout :title="$pageTitle">
    <div class="hv-container max-w-xl py-16">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-50">{{ $pageTitle }}</h1>
        @if (app()->getLocale() === 'tr')
            <p class="mt-4 text-slate-600 dark:text-slate-400">
                Ödemeniz alındıysa lisans anahtarınız kısa süre içinde e-posta adresinize gönderilir.
                Bildirim URL’si (callback) tamamlandıktan sonra sipariş durumunu aşağıdaki API ile sorgulayabilirsiniz.
            </p>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-500">
                Sipariş referansı: <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">{{ request('ref', '—') }}</code>
            </p>
        @else
            <p class="mt-4 text-slate-600 dark:text-slate-400">
                If your payment succeeded, your license key will be emailed shortly after our server confirms it.
                You can poll order status with the licensing API using your email and order reference.
            </p>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-500">
                Order reference: <code class="rounded bg-slate-100 px-1 font-mono text-xs dark:bg-slate-800">{{ request('ref', '—') }}</code>
            </p>
        @endif
        <p class="mt-8">
            <a href="{{ route('site.pricing') }}" class="hv-link-quiet font-semibold">{{ app()->getLocale() === 'tr' ? 'Fiyatlandırmaya dön' : 'Back to pricing' }}</a>
        </p>
    </div>
</x-site.layout>
