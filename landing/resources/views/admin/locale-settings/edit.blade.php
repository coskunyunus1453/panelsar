<x-admin.layout title="Dil ayarları">
    <div class="max-w-2xl space-y-6">
        <div>
            <h1 class="admin-page-title">Site dilleri</h1>
            <p class="admin-muted mt-1">Ziyaretçiler için varsayılan dili ve hangi dillerin menüde sunulacağını belirleyin.</p>
        </div>

        <form method="post" action="{{ route('admin.locale-settings.update') }}" class="admin-form-panel space-y-6">
            @csrf
            @method('put')

            <div>
                <label class="admin-label-block" for="default_locale">Varsayılan dil</label>
                <select id="default_locale" name="default_locale" class="admin-field mt-2">
                    @foreach ($locales as $code => $label)
                        @if (in_array($code, $enabled, true))
                            <option value="{{ $code }}" @selected($default === $code)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
                @error('default_locale')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <span class="admin-label-block">Etkin diller</span>
                <ul class="mt-3 space-y-2">
                    @foreach ($locales as $code => $label)
                        <li class="flex items-center gap-2">
                            <input type="checkbox" name="enabled_locales[]" value="{{ $code }}" id="loc_{{ $code }}"
                                   class="admin-checkbox"
                                   @checked(in_array($code, $enabled, true))>
                            <label for="loc_{{ $code }}" class="text-sm text-slate-700 dark:text-slate-300">{{ $label }} ({{ $code }})</label>
                        </li>
                    @endforeach
                </ul>
                @error('enabled_locales')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="inline-flex rounded-full bg-orange-500 px-5 py-2 text-sm font-semibold text-white hover:bg-orange-400">
                Kaydet
            </button>
        </form>

        <p class="text-xs text-slate-600 dark:text-slate-500">Çeviri metinlerini düzenlemek için <a href="{{ route('admin.translations.index') }}" class="admin-link">Çeviriler</a> sayfasını kullanın. Dil dosyaları: <code class="rounded bg-slate-200 px-1 font-mono text-slate-800 dark:bg-slate-800 dark:text-slate-300">lang/&lt;locale&gt;/landing.php</code></p>
    </div>
</x-admin.layout>
