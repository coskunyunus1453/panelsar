<x-admin.layout title="Çeviriler (Landing)">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="admin-page-title">Önyüz çevirileri</h1>
                <p class="admin-muted mt-1 max-w-xl">Önce <code class="rounded bg-slate-200 px-1 py-0.5 font-mono text-xs text-slate-800 dark:bg-slate-800 dark:text-slate-200">lang/&lt;locale&gt;/landing.php</code> dosyalarını düzenleyin. Acil düzeltmeler için buradan veritabanı üzerine yazabilirsiniz (dosyayı geçersiz kılar).</p>
            </div>
            <form method="get" action="{{ route('admin.translations.index') }}" class="flex items-center gap-2">
                <label for="locale" class="text-xs text-slate-600 dark:text-slate-500">Dil</label>
                <select id="locale" name="locale" onchange="this.form.submit()" class="admin-field w-auto min-w-[10rem] px-3 py-2">
                    @foreach ($enabledLocales as $code)
                        <option value="{{ $code }}" @selected($locale === $code)>{{ $localeLabels[$code] ?? $code }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="admin-table-wrap !mt-0">
            <table class="min-w-full text-left text-sm">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-4 py-3">Anahtar</th>
                        <th class="px-4 py-3">TR (dosya)</th>
                        <th class="px-4 py-3">Geçerli ({{ $locale }})</th>
                        <th class="w-24 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="admin-table-body">
                    @foreach ($rows as $row)
                        <tr class="admin-table-row">
                            <td class="px-4 py-2 font-mono text-xs">
                                <span class="admin-key">{{ $row['key'] }}</span>
                            </td>
                            <td class="admin-td-muted px-4 py-2 text-xs max-w-xs truncate">{{ \Illuminate\Support\Str::limit($row['base_tr'], 80) }}</td>
                            <td class="px-4 py-2 text-xs text-slate-800 dark:text-slate-200 max-w-md">
                                @if ($row['has_override'])
                                    <span class="mr-1 rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold text-orange-800 dark:bg-orange-500/15 dark:text-orange-200">DB</span>
                                @endif
                                {{ \Illuminate\Support\Str::limit($row['effective'], 120) }}
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.translations.edit', ['key' => $row['key'], 'locale' => $locale]) }}"
                                   class="text-xs font-medium text-orange-600 hover:text-orange-800 dark:text-orange-400 dark:hover:text-orange-300">Düzenle</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin.layout>
