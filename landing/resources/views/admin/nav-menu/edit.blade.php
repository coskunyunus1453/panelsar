@php
    use App\Models\NavMenuItem;
@endphp
<x-admin.layout title="Menü — düzenle">
    <form method="post" action="{{ route('admin.nav-menu.update', $item) }}" class="mx-auto max-w-xl space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="zone" class="admin-label">Bölüm</label>
            <select id="zone" name="zone" class="admin-field mt-1">
                <option value="{{ NavMenuItem::ZONE_HEADER }}" @selected(old('zone', $item->zone) === NavMenuItem::ZONE_HEADER)>Üst menü</option>
                <option value="{{ NavMenuItem::ZONE_FOOTER }}" @selected(old('zone', $item->zone) === NavMenuItem::ZONE_FOOTER)>Alt menü (footer)</option>
            </select>
            @error('zone')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="label" class="admin-label">Etiket (Türkçe / varsayılan)</label>
            <input id="label" name="label" type="text" value="{{ old('label', $item->label) }}" required class="admin-field mt-1" />
            @error('label')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="label_en" class="admin-label">İngilizce etiket</label>
            <input id="label_en" name="label_en" type="text" value="{{ old('label_en', $item->label_en) }}" class="admin-field mt-1" />
            <p class="admin-muted mt-1 text-xs">Dil EN iken gösterilir; boşsa varsayılan etiket kullanılır.</p>
            @error('label_en')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="href" class="admin-label">Bağlantı (varsayılan)</label>
            <input id="href" name="href" type="text" value="{{ old('href', $item->href) }}" required class="admin-field mt-1 font-mono text-sm" />
            @error('href')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="href_en" class="admin-label">İngilizce bağlantı (isteğe bağlı)</label>
            <input id="href_en" name="href_en" type="text" value="{{ old('href_en', $item->href_en) }}" class="admin-field mt-1 font-mono text-sm" />
            <p class="admin-muted mt-1 text-xs">Farklı slug veya harici adres gerekiyorsa doldurun.</p>
            @error('href_en')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="sort_order" class="admin-label">Sıra</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="500" value="{{ old('sort_order', $item->sort_order) }}" required class="admin-field mt-1 w-28" />
            @error('sort_order')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active)) class="admin-checkbox" />
            Yayında
        </label>

        <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="open_in_new_tab" value="1" @checked(old('open_in_new_tab', $item->open_in_new_tab)) class="admin-checkbox" />
            Yeni sekmede aç
        </label>

        <div class="flex gap-3">
            <button type="submit" class="admin-btn-emerald-lg">Güncelle</button>
            <a href="{{ route('admin.nav-menu.index') }}" class="admin-btn-outline">Listeye dön</a>
        </div>
    </form>
</x-admin.layout>
