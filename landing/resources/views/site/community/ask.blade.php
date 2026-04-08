<x-site.layout
    :title="$seoTitle"
    :description="$seoDescription"
    :canonical-url="$canonicalUrl"
    :robots-content="$robotsContent"
>
    <div class="hv-container max-w-2xl py-10">
        <nav class="mb-6 text-sm text-slate-600 dark:text-slate-400" aria-label="İçerik yolu">
            <a href="{{ route('community.index') }}" class="hover:text-[rgb(var(--hv-brand-600)/1)]">{{ $site->site_title }}</a>
            <span class="mx-2">/</span>
            <span class="text-slate-900 dark:text-slate-200">Yeni soru</span>
        </nav>

        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-50">Soru sor</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Kategori seçin, başlığı net yazın. İsteğe bağlı SEO alanları arama sonuçlarında görünen başlık ve açıklamayı özelleştirir.</p>

        @if ($categories->isEmpty())
            <p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                Henüz kategori yok. Yönetim panelinden <strong>Topluluk → Kategoriler</strong> ile en az bir kategori ekleyin.
            </p>
        @else
            <form method="post" action="{{ route('community.ask.store') }}" class="relative mt-8 space-y-4">
                @csrf
                <x-community.honeypot />
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Kategori</label>
                    <select name="community_category_id" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900">
                        <option value="">Seçin…</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('community_category_id', $preselectedCategory?->id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('community_category_id')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Başlık</label>
                    <input type="text" name="title" value="{{ old('title') }}" required maxlength="200" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900" />
                    @error('title')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">İçerik</label>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Biçimlendirme araç çubuğundan kullanılabilir. Gönderilen HTML sunucuda tekrar temizlenir; yalnızca güvenli etiketler saklanır.</p>
                    <x-admin.rich-editor
                        name="body"
                        :value="old('body')"
                        id="community-ask-body"
                        placeholder="Sorunuzu ayrıntılı yazın…"
                        min-height="clamp(14rem, 42vh, 28rem)"
                    />
                    @error('body')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Etiketler (isteğe bağlı)</label>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">En fazla 5 etiket; virgülle ayırın (örn. <span class="font-mono">vps, dns, yedekleme</span>).</p>
                    <input type="text" name="tags" value="{{ old('tags') }}" maxlength="200" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900" />
                    @error('tags')
                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <details class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-200">Gelişmiş — arama / SEO (isteğe bağlı)</summary>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Boş bırakırsanız başlık ve özet otomatik kullanılır. Yöneticiler tüm konuları düzenleyebilir.</p>
                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">Meta başlık (en fazla 70 karakter)</label>
                            <input type="text" name="meta_title" value="{{ old('meta_title') }}" maxlength="70" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                            @error('meta_title')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">Meta açıklama (en fazla 200 karakter)</label>
                            <textarea name="meta_description" rows="3" maxlength="200" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900">{{ old('meta_description') }}</textarea>
                            @error('meta_description')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </details>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-5 py-2.5 text-sm font-semibold text-white">Yayınla</button>
                    <a href="{{ route('community.index') }}" class="text-sm font-medium text-slate-600 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">İptal</a>
                </div>
            </form>
        @endif
    </div>
</x-site.layout>
