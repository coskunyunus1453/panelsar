<x-admin.layout title="Çeviri: {{ $key }}">
    <div class="max-w-3xl space-y-6">
        <div>
            <a href="{{ route('admin.translations.index', ['locale' => $locale]) }}" class="admin-link text-xs">← Listeye dön</a>
            <h1 class="admin-page-title mt-3">Çeviri düzenle</h1>
            <p class="admin-key mt-1">{{ $key }}</p>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">Dil: <strong class="text-slate-800 dark:text-slate-300">{{ $locale }}</strong></p>
        </div>

        <div class="admin-panel-info">
            <p class="admin-kicker">Dil dosyasından (referans)</p>
            <p class="admin-text-body mt-2 whitespace-pre-wrap">{{ $fileVal }}</p>
        </div>

        <form method="post" action="{{ route('admin.translations.update') }}" class="admin-form-panel space-y-4">
            @csrf
            @method('put')
            <input type="hidden" name="key" value="{{ $key }}">
            <input type="hidden" name="locale" value="{{ $locale }}">

            <div>
                <label for="value" class="admin-label-block">Özel metin (veritabanı)</label>
                <textarea id="value" name="value" rows="6"
                          class="admin-field mt-2"
                          placeholder="Boş bırakırsanız dil dosyasındaki metin kullanılır.">{{ old('value', $override) }}</textarea>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-500">İpucu: <code class="rounded bg-slate-200 px-1 font-mono text-slate-800 dark:bg-slate-800 dark:text-slate-300">:brand</code> gibi yer tutucuları koruyun.</p>
                @error('value')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="inline-flex rounded-full bg-orange-500 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-400">
                    Kaydet
                </button>
                <button type="submit" name="reset" value="1" class="admin-btn-outline"
                        onclick="return confirm('Özel çeviri silinsin mi?');">
                    Dosyaya sıfırla
                </button>
            </div>
        </form>
    </div>
</x-admin.layout>
