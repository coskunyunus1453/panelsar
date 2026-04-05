<x-admin.layout title="Yeni müşteri">
    <form method="POST" action="{{ route('admin.saas.customers.store') }}" class="max-w-xl space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium">Ad *</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">E-posta</label>
            <input type="email" name="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Şirket</label>
            <input type="text" name="company" value="{{ old('company') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Telefon</label>
            <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Durum</label>
            <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <option value="active" @selected(old('status', 'active') === 'active')>Aktif</option>
                <option value="suspended" @selected(old('status') === 'suspended')>Askıda</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Notlar</label>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('notes') }}</textarea>
        </div>
        <button type="submit" class="admin-btn-emerald">Kaydet</button>
    </form>
</x-admin.layout>
