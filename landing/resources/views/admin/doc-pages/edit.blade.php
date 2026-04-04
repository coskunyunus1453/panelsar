<x-admin.layout title="Doküman düzenle">
    <form method="POST" action="{{ route('admin.doc-pages.update', $page) }}" class="mx-auto max-w-2xl space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="locale" class="admin-label">Dil (içerik)</label>
            <select id="locale" name="locale" class="admin-field mt-1" required>
                @foreach (config('landing.locales') as $code => $label)
                    <option value="{{ $code }}" @selected(old('locale', $page->locale) === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @error('locale')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="parent_id" class="admin-label">Üst sayfa (isteğe bağlı)</label>
            <select id="parent_id" name="parent_id"
                    class="admin-field mt-1">
                <option value="">— Kök —</option>
                @foreach ($parents as $p)
                    <option value="{{ $p->id }}" @selected(old('parent_id', $page->parent_id) == $p->id)>{{ $p->title }} ({{ $p->slug }})</option>
                @endforeach
            </select>
            @error('parent_id')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="slug" class="admin-label">Slug</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $page->slug) }}" required
                   class="admin-field mt-1" />
            @error('slug')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="title" class="admin-label">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title', $page->title) }}" required
                   class="admin-field mt-1" />
            @error('title')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="admin-form-panel space-y-4 !shadow-none">
            <p class="admin-label-block">SEO</p>
            <div>
                <label for="meta_title" class="admin-label">Meta başlık</label>
                <input id="meta_title" name="meta_title" type="text" value="{{ old('meta_title', $page->meta_title) }}" class="admin-field mt-1" />
                @error('meta_title')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="meta_description" class="admin-label">Meta açıklama</label>
                <textarea id="meta_description" name="meta_description" rows="2" class="admin-field mt-1">{{ old('meta_description', $page->meta_description) }}</textarea>
                @error('meta_description')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="content" class="admin-label">İçerik</label>
            <x-admin.rich-editor name="content" :value="old('content', $page->content)" />
            <p class="mt-1 text-[11px] text-slate-600 dark:text-slate-500">Quill ile zengin metin. Eski Markdown içerik ön yüzde çalışmaya devam eder; kaydettiğinizde HTML saklanır.</p>
            @error('content')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published)) class="admin-checkbox" />
                Yayında
            </label>
            <div class="flex items-center gap-2">
                <label for="sort_order" class="text-xs text-slate-600 dark:text-slate-400">Sıra</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $page->sort_order) }}"
                       class="admin-field w-24 px-3 py-1.5" />
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="admin-btn-emerald-lg">
                Güncelle
            </button>
            <a href="{{ route('admin.doc-pages.index') }}" class="admin-btn-outline">
                Listeye dön
            </a>
        </div>
    </form>
</x-admin.layout>
