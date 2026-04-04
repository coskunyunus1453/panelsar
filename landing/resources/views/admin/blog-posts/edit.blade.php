<x-admin.layout title="Blog yazısı düzenle">
    <form method="POST" action="{{ route('admin.blog-posts.update', $post) }}" class="mx-auto max-w-2xl space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="locale" class="admin-label">Dil (içerik)</label>
            <select id="locale" name="locale" class="admin-field mt-1" required>
                @foreach (config('landing.locales') as $code => $label)
                    <option value="{{ $code }}" @selected(old('locale', $post->locale) === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @error('locale')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="slug" class="admin-label">Slug</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $post->slug) }}" required
                   class="admin-field mt-1" />
            @error('slug')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="title" class="admin-label">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title', $post->title) }}" required
                   class="admin-field mt-1" />
            @error('title')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="blog_category_id" class="admin-label">Kategori (SEO)</label>
            <select id="blog_category_id" name="blog_category_id" class="admin-field mt-1">
                <option value="">— Yok —</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('blog_category_id', $post->blog_category_id) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('blog_category_id')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="excerpt" class="admin-label">Özet</label>
            <textarea id="excerpt" name="excerpt" rows="3"
                      class="admin-field mt-1">{{ old('excerpt', $post->excerpt) }}</textarea>
            @error('excerpt')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="admin-form-panel space-y-4 !shadow-none">
            <p class="admin-label-block">SEO</p>
            <div>
                <label for="meta_title" class="admin-label">Meta başlık</label>
                <input id="meta_title" name="meta_title" type="text" value="{{ old('meta_title', $post->meta_title) }}" class="admin-field mt-1" />
                @error('meta_title')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="meta_description" class="admin-label">Meta açıklama</label>
                <textarea id="meta_description" name="meta_description" rows="2" class="admin-field mt-1">{{ old('meta_description', $post->meta_description) }}</textarea>
                @error('meta_description')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="canonical_url" class="admin-label">Kanonik URL</label>
                <input id="canonical_url" name="canonical_url" type="text" value="{{ old('canonical_url', $post->canonical_url) }}" class="admin-field mt-1 font-mono text-sm" />
                @error('canonical_url')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="og_image" class="admin-label">Open Graph görseli</label>
                <input id="og_image" name="og_image" type="text" value="{{ old('og_image', $post->og_image) }}" class="admin-field mt-1 font-mono text-sm" />
                @error('og_image')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="robots" class="admin-label">Robots</label>
                <input id="robots" name="robots" type="text" value="{{ old('robots', $post->robots) }}" class="admin-field mt-1 font-mono text-sm" />
                @error('robots')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="content" class="admin-label">İçerik</label>
            <x-admin.rich-editor name="content" :value="old('content', $post->content)" />
            <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-500">Quill ile zengin metin. Eski Markdown kayıtları ön yüzde aynı şekilde çalışır; düzenleyip kaydedince HTML olarak saklanır.</p>
            @error('content')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="published_at" class="admin-label">Yayın tarihi</label>
            <input id="published_at" name="published_at" type="datetime-local"
                   value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"
                   class="admin-field mt-1 max-w-md" />
            @error('published_at')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published)) class="admin-checkbox" />
            Yayında
        </label>

        <div class="flex gap-3">
            <button type="submit" class="admin-btn-emerald-lg">
                Güncelle
            </button>
            <a href="{{ route('admin.blog-posts.index') }}" class="admin-btn-outline">
                Listeye dön
            </a>
        </div>
    </form>
</x-admin.layout>
