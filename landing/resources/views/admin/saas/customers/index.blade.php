<x-admin.layout title="SaaS — müşteriler">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Lisans satın alan kurum / kişi kayıtları.</p>
        <a href="{{ route('admin.saas.customers.create') }}" class="admin-btn-emerald">Yeni müşteri</a>
    </div>
    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Ad</th>
                    <th class="px-4 py-3">E-posta</th>
                    <th class="px-4 py-3">Durum</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($customers as $c)
                    <tr class="admin-table-row">
                        <td class="px-4 py-3 admin-td-strong">{{ $c->name }}</td>
                        <td class="px-4 py-3 admin-td-soft">{{ $c->email ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $c->status === 'active' ? 'Aktif' : 'Askıda' }}</td>
                        <td class="px-4 py-3 text-right text-xs">
                            <a href="{{ route('admin.saas.customers.edit', $c) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.saas.customers.destroy', $c) }}" method="POST" class="inline" onsubmit="return confirm('Silinsin mi?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-2 text-rose-600">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $customers->links() }}</div>
</x-admin.layout>
