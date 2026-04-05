<x-admin.layout title="Müşteri düzenle">
    <form method="POST" action="{{ route('admin.saas.customers.update', $customer) }}" class="max-w-xl space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium">Ad *</label>
            <input type="text" name="name" value="{{ old('name', $customer->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">E-posta</label>
            <input type="email" name="email" value="{{ old('email', $customer->email) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Şirket</label>
            <input type="text" name="company" value="{{ old('company', $customer->company) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Telefon</label>
            <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <div>
            <label class="block text-sm font-medium">Durum</label>
            <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <option value="active" @selected(old('status', $customer->status) === 'active')>Aktif</option>
                <option value="suspended" @selected(old('status', $customer->status) === 'suspended')>Askıda</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Notlar</label>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('notes', $customer->notes) }}</textarea>
        </div>
        <button type="submit" class="admin-btn-emerald">Güncelle</button>
    </form>
</x-admin.layout>
