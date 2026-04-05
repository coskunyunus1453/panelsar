<x-admin.layout title="Hostvim SaaS — özet">
    <p class="admin-muted mb-6">Müşteri sunucularındaki panel <code class="rounded bg-slate-200 px-1 text-xs dark:bg-slate-800">LICENSE_SERVER_URL</code> ile bu siteye bağlanıp lisans doğrulayabilir.</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-800 dark:bg-slate-900/60">
            <div class="text-xs text-slate-500">Müşteriler</div>
            <div class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $customers }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-800 dark:bg-slate-900/60">
            <div class="text-xs text-slate-500">Aktif lisans</div>
            <div class="text-2xl font-semibold text-emerald-700 dark:text-emerald-400">{{ $licenses_active }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-800 dark:bg-slate-900/60">
            <div class="text-xs text-slate-500">Ürün (tier)</div>
            <div class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $products }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-800 dark:bg-slate-900/60">
            <div class="text-xs text-slate-500">Modül tanımı</div>
            <div class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $modules }}</div>
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-200/90 bg-white/90 p-4 text-sm dark:border-slate-800 dark:bg-slate-900/60">
        <div class="font-medium text-slate-900 dark:text-slate-100">API doğrulama uç noktası</div>
        <p class="mt-1 text-slate-600 dark:text-slate-400">POST JSON <code class="text-xs">{"key":"hv_..."}</code></p>
        <code class="mt-2 block break-all rounded-lg bg-slate-100 p-3 text-xs dark:bg-slate-950">{{ $api_endpoint }}</code>
        <p class="mt-2 text-xs text-slate-500">İsteğe bağlı: <code>HOSTVIM_LICENSE_API_SECRET</code> tanımlıysa <code>Authorization: Bearer …</code> gerekir.</p>
    </div>
</x-admin.layout>
