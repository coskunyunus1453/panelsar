<x-admin.layout title="SaaS — lisans ürünleri">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Community / Pro gibi tier kodları; API’de <code class="text-xs">plan</code> alanı.</p>
        <a href="{{ route('admin.saas.products.create') }}" class="admin-btn-emerald">Yeni ürün</a>
    </div>
    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Kod</th>
                    <th class="px-4 py-3">Ad</th>
                    <th class="px-4 py-3">PayTR (kr)</th>
                    <th class="px-4 py-3">Stripe (¢)</th>
                    <th class="px-4 py-3">Aktif</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($products as $p)
                    <tr class="admin-table-row">
                        <td class="px-4 py-3 font-mono text-xs">{{ $p->code }}</td>
                        <td class="px-4 py-3 admin-td-strong">{{ $p->name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">{{ $p->price_try_minor ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">{{ $p->price_usd_minor ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $p->is_active ? 'Evet' : 'Hayır' }}</td>
                        <td class="px-4 py-3 text-right text-xs">
                            <a href="{{ route('admin.saas.products.edit', $p) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.saas.products.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('Silinsin mi?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-2 text-rose-600">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $products->links() }}</div>
</x-admin.layout>
