<x-admin.layout title="Yeni lisans ürünü">
    <form method="POST" action="{{ route('admin.saas.products.store') }}" class="max-w-2xl space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium">Kod * <span class="text-slate-500">(ör. community, pro)</span></label>
            <input type="text" name="code" value="{{ old('code') }}" required pattern="[a-z0-9_\-]+" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Ad *</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Açıklama</label>
            <input type="text" name="description" value="{{ old('description') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', true)) class="rounded border-slate-300">
            <label for="is_active">Aktif</label>
        </div>
        <div>
            <label class="block text-sm font-medium">Sıra</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="mt-1 w-32 rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Varsayılan limitler (JSON)</label>
            <textarea name="default_limits_raw" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900" placeholder='{"max_sites": 10}'>{{ old('default_limits_raw', '{}') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Varsayılan modüller (JSON — anahtar: true/false)</label>
            <textarea name="default_modules_raw" rows="6" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900" placeholder='{"vendor_panel": false, "backups_pro": true}'>{{ old('default_modules_raw', '{}') }}</textarea>
        </div>
        <button type="submit" class="admin-btn-emerald">Kaydet</button>
    </form>
</x-admin.layout>
