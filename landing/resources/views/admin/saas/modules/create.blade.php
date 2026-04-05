<x-admin.layout title="Yeni modül">
    <form method="POST" action="{{ route('admin.saas.modules.store') }}" class="max-w-xl space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium">Anahtar * <span class="text-slate-500">(snake_case)</span></label>
            <input type="text" name="key" value="{{ old('key') }}" required pattern="[a-z0-9_]+" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Etiket *</label>
            <input type="text" name="label" value="{{ old('label') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Açıklama</label>
            <input type="text" name="description" value="{{ old('description') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div class="flex flex-wrap gap-4">
            <label class="flex items-center gap-2">
                <input type="hidden" name="is_paid" value="0">
                <input type="checkbox" name="is_paid" value="1" @checked(old('is_paid'))> Ücretli
            </label>
            <label class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Aktif (API’de listelenir)
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium">Sıra</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="mt-1 w-32 rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <button type="submit" class="admin-btn-emerald">Kaydet</button>
    </form>
</x-admin.layout>
