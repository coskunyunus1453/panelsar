@php
    use App\Models\NavMenuItem;
@endphp
<x-admin.layout title="Menü — yeni bağlantı">
    <form method="post" action="{{ route('admin.nav-menu.store') }}" class="mx-auto max-w-xl space-y-5">
        @csrf
        <input type="hidden" name="zone" value="{{ $zone }}">

        <div>
            <p class="admin-label-block">Bölüm</p>
            <p class="admin-muted mt-1">{{ $zone === NavMenuItem::ZONE_HEADER ? 'Üst menü (başlık)' : 'Alt menü (footer)' }}</p>
        </div>

        <div>
            <label for="label" class="admin-label">Etiket</label>
            <input id="label" name="label" type="text" value="{{ old('label') }}" required class="admin-field mt-1" />
            @error('label')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="href" class="admin-label">Bağlantı</label>
            <input id="href" name="href" type="text" value="{{ old('href') }}" required class="admin-field mt-1 font-mono text-sm" placeholder="/blog veya https://..." />
            @error('href')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="admin-checkbox" />
            Yayında (listede göster)
        </label>

        <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="open_in_new_tab" value="1" @checked(old('open_in_new_tab')) class="admin-checkbox" />
            Yeni sekmede aç
        </label>

        <div class="flex gap-3">
            <button type="submit" class="admin-btn-emerald-lg">Kaydet</button>
            <a href="{{ route('admin.nav-menu.index') }}" class="admin-btn-outline">İptal</a>
        </div>
    </form>
</x-admin.layout>
