<x-admin.layout title="Özet">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="admin-card p-4">
            <div class="admin-kicker">Durum</div>
            <div class="admin-stat-ok mt-2">Çalışıyor</div>
            <div class="mt-1 text-xs text-slate-600 dark:text-slate-400">Landing ve admin paneli hazır.</div>
        </div>
        <div class="admin-card p-4">
            <div class="admin-kicker">İçerik</div>
            <div class="mt-2 text-sm font-medium text-slate-800 dark:text-slate-100">Site, blog, docs, planlar</div>
            <div class="mt-1 text-xs text-slate-600 dark:text-slate-400">Sol menüden modüllere geçin veya <a href="{{ route('landing.home') }}" class="admin-link-emerald">siteyi</a> açın.</div>
        </div>
        <div class="admin-card p-4 sm:col-span-2 lg:col-span-1">
            <div class="admin-kicker">Oturum</div>
            <div class="mt-2 text-sm text-slate-800 dark:text-slate-200">{{ auth()->user()->name }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ auth()->user()->email }}</div>
        </div>
    </div>
</x-admin.layout>
