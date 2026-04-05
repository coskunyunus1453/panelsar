<x-admin.layout title="SaaS — lisanslar">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Anahtarlar müşteri panelinde <code class="text-xs">LICENSE_KEY</code> olarak girilir.</p>
        <a href="{{ route('admin.saas.licenses.create') }}" class="admin-btn-emerald">Yeni lisans</a>
    </div>
    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Anahtar</th>
                    <th class="px-4 py-3">Müşteri</th>
                    <th class="px-4 py-3">Ürün</th>
                    <th class="px-4 py-3">Durum</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($licenses as $lic)
                    <tr class="admin-table-row">
                        <td class="px-4 py-3 font-mono text-[11px] break-all">{{ $lic->license_key }}</td>
                        <td class="px-4 py-3">{{ $lic->customer->name }}</td>
                        <td class="px-4 py-3">{{ $lic->product->name }}</td>
                        <td class="px-4 py-3">{{ $lic->status }}</td>
                        <td class="px-4 py-3 text-right text-xs">
                            <a href="{{ route('admin.saas.licenses.edit', $lic) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.saas.licenses.destroy', $lic) }}" method="POST" class="inline" onsubmit="return confirm('Silinsin mi?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-2 text-rose-600">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $licenses->links() }}</div>
</x-admin.layout>
