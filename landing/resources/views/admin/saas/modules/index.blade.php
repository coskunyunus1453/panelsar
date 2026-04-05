<x-admin.layout title="SaaS — modüller">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Ücretli özellik bayrakları; ürün JSON’unda anahtar adlarıyla eşleşmeli.</p>
        <a href="{{ route('admin.saas.modules.create') }}" class="admin-btn-emerald">Yeni modül</a>
    </div>
    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Anahtar</th>
                    <th class="px-4 py-3">Etiket</th>
                    <th class="px-4 py-3">Ücretli</th>
                    <th class="px-4 py-3">Aktif</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($modules as $m)
                    <tr class="admin-table-row">
                        <td class="px-4 py-3 font-mono text-xs">{{ $m->key }}</td>
                        <td class="px-4 py-3">{{ $m->label }}</td>
                        <td class="px-4 py-3">{{ $m->is_paid ? 'Evet' : 'Hayır' }}</td>
                        <td class="px-4 py-3">{{ $m->is_active ? 'Açık' : 'Kapalı' }}</td>
                        <td class="px-4 py-3 text-right text-xs">
                            <a href="{{ route('admin.saas.modules.edit', $m) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.saas.modules.destroy', $m) }}" method="POST" class="inline" onsubmit="return confirm('Silinsin mi?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-2 text-rose-600">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $modules->links() }}</div>
</x-admin.layout>
