<x-admin.layout title="Fiyat planları">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="admin-muted">Fiyatlandırma sayfasında gösterilen kartlar.</p>
        <a href="{{ route('admin.plans.create') }}" class="admin-btn-emerald">
            Yeni plan
        </a>
    </div>

    <div class="admin-table-wrap">
        <table class="min-w-full text-left text-sm">
            <thead class="admin-table-head">
                <tr>
                    <th class="px-4 py-3">Ad</th>
                    <th class="px-4 py-3">Fiyat</th>
                    <th class="px-4 py-3">Öne çıkan</th>
                    <th class="px-4 py-3">Aktif</th>
                    <th class="px-4 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="admin-table-body">
                @foreach ($plans as $plan)
                    <tr class="admin-table-row">
                        <td class="px-4 py-3">
                            <div class="admin-td-strong">{{ $plan->name }}</div>
                            <div class="font-mono text-[11px] text-slate-500">{{ $plan->slug }}</div>
                        </td>
                        <td class="admin-td-soft px-4 py-3">{{ $plan->price_label }}</td>
                        <td class="px-4 py-3">
                            @if ($plan->is_featured)
                                <span class="text-emerald-700 dark:text-emerald-400">Evet</span>
                            @else
                                <span class="admin-td-muted">Hayır</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($plan->is_active)
                                <span class="admin-badge-ok">Aktif</span>
                            @else
                                <span class="admin-badge-off">Kapalı</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-xs">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="admin-link-emerald">Düzenle</a>
                            <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST" class="inline" onsubmit="return confirm('Silinsin mi?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-rose-600 hover:text-rose-800 dark:text-rose-400/90 dark:hover:text-rose-300">Sil</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $plans->links() }}
    </div>
</x-admin.layout>
