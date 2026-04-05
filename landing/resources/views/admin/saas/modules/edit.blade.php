<x-admin.layout title="Modül düzenle">
    <form method="POST" action="{{ route('admin.saas.modules.update', $module) }}" class="max-w-xl space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium">Anahtar *</label>
            <input type="text" name="key" value="{{ old('key', $module->key) }}" required pattern="[a-z0-9_]+" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Etiket *</label>
            <input type="text" name="label" value="{{ old('label', $module->label) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Açıklama</label>
            <input type="text" name="description" value="{{ old('description', $module->description) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div class="flex flex-wrap gap-4">
            <label class="flex items-center gap-2">
                <input type="hidden" name="is_paid" value="0">
                <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid', $module->is_paid))> Ücretli
            </label>
            <label class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $module->is_active))> Aktif
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium">Sıra</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $module->sort_order) }}" min="0" class="mt-1 w-32 rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <button type="submit" class="admin-btn-emerald">Güncelle</button>
    </form>
</x-admin.layout>
