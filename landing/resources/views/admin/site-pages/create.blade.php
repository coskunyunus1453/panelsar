<x-admin.layout title="Yeni site sayfası">
    <form method="POST" action="{{ route('admin.site-pages.store') }}" class="mx-auto max-w-2xl space-y-5">
        @csrf

        <div>
            <label for="locale" class="admin-label">Dil (içerik)</label>
            <select id="locale" name="locale" class="admin-field mt-1" required>
                @foreach (config('landing.locales') as $code => $label)
                    <option value="{{ $code }}" @selected(old('locale', config('app.locale')) === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @error('locale')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="slug" class="admin-label">Slug (URL)</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required
                   class="admin-field mt-1 font-mono text-sm" />
            @error('slug')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-[11px] text-slate-500">Küçük harf ve tire (ör. <span class="font-mono">about</span>). Yayında: <span class="font-mono">/p/slug</span> — slug <span class="font-mono">setup</span> ise <span class="font-mono">/setup</span> kullanılır.</p>
        </div>

        <div>
            <label for="title" class="admin-label">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" required
                   class="admin-field mt-1" />
            @error('title')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="meta_title" class="admin-label">Meta başlık (SEO)</label>
            <input id="meta_title" name="meta_title" type="text" value="{{ old('meta_title') }}" class="admin-field mt-1" placeholder="Boşsa sayfa başlığı kullanılır" />
            @error('meta_title')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="content" class="admin-label">İçerik</label>
            <x-admin.rich-editor name="content" :value="old('content', '')" />
            <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-500">Quill ile zengin metin; kayıt güvenli HTML olarak saklanır.</p>
            @error('content')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="admin-form-panel space-y-4 !shadow-none">
            <p class="admin-label-block">SEO</p>
            <div>
                <label for="meta_description" class="admin-label">Meta açıklama</label>
                <textarea id="meta_description" name="meta_description" rows="2" class="admin-field mt-1" placeholder="Boşsa içerikten kısa özet üretilir">{{ old('meta_description') }}</textarea>
                @error('meta_description')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="canonical_url" class="admin-label">Kanonik URL (isteğe bağlı)</label>
                <input id="canonical_url" name="canonical_url" type="text" value="{{ old('canonical_url') }}" class="admin-field mt-1 font-mono text-sm" placeholder="/p/slug veya https://..." />
                @error('canonical_url')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="og_image" class="admin-label">Open Graph görseli (isteğe bağlı)</label>
                <input id="og_image" name="og_image" type="text" value="{{ old('og_image') }}" class="admin-field mt-1 font-mono text-sm" placeholder="/images/og.jpg veya https://..." />
                @error('og_image')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="robots" class="admin-label">Robots (isteğe bağlı)</label>
                <input id="robots" name="robots" type="text" value="{{ old('robots') }}" class="admin-field mt-1 font-mono text-sm" placeholder="noindex, nofollow" />
                @error('robots')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', true)) class="admin-checkbox" />
                Yayında
            </label>
            <div class="flex items-center gap-2">
                <label for="sort_order" class="text-xs text-slate-600 dark:text-slate-400">Sıra</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}"
                       class="admin-field w-24 px-3 py-1.5" />
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="admin-btn-emerald-lg">
                Kaydet
            </button>
            <a href="{{ route('admin.site-pages.index') }}" class="admin-btn-outline">
                İptal
            </a>
        </div>
    </form>
</x-admin.layout>
