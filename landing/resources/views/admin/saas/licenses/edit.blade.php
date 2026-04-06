<x-admin.layout title="Lisans düzenle">
    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-900/50">
        <div class="font-mono text-xs break-all">{{ $license->license_key }}</div>
        <form method="POST" action="{{ route('admin.saas.licenses.regenerate', $license) }}" class="mt-2" onsubmit="return confirm('Eski anahtar geçersiz olur. Devam?');">
            @csrf
            <button type="submit" class="text-xs text-orange-700 underline dark:text-orange-400">Anahtarı yeniden üret</button>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.saas.licenses.update', $license) }}" class="max-w-2xl space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium">Müşteri *</label>
            <select name="saas_customer_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                @foreach ($customers as $c)
                    <option value="{{ $c->id }}" @selected(old('saas_customer_id', $license->saas_customer_id) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Ürün *</label>
            <select name="saas_license_product_id" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                @foreach ($products as $p)
                    <option value="{{ $p->id }}" @selected(old('saas_license_product_id', $license->saas_license_product_id) == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Durum *</label>
            <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                @foreach (['active', 'suspended', 'expired', 'revoked'] as $st)
                    <option value="{{ $st }}" @selected(old('status', $license->status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">Başlangıç</label>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($license->starts_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            </div>
            <div>
                <label class="block text-sm font-medium">Bitiş</label>
                <input type="datetime-local" name="expires_at" value="{{ old('expires_at', optional($license->expires_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium">Abonelik durumu</label>
            <select name="subscription_status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <option value="">—</option>
                @foreach (['active', 'past_due', 'canceled', 'none'] as $st)
                    <option value="{{ $st }}" @selected(old('subscription_status', $license->subscription_status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Yenileme tarihi</label>
            <input type="datetime-local" name="subscription_renews_at" value="{{ old('subscription_renews_at', optional($license->subscription_renews_at)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Limit özelleştirme (JSON)</label>
            <textarea name="limits_override_raw" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900">{{ $limits_override_raw }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Modül özelleştirme (JSON)</label>
            <textarea name="modules_override_raw" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900">{{ $modules_override_raw }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Notlar</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('notes', $license->notes) }}</textarea>
        </div>
        <button type="submit" class="admin-btn-emerald">Güncelle</button>
    </form>
</x-admin.layout>
