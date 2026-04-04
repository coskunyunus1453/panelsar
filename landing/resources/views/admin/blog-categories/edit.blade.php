<x-admin.layout title="Blog kategorisi düzenle">
    <form method="post" action="{{ route('admin.blog-categories.update', $category) }}" class="mx-auto max-w-xl space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label for="locale" class="admin-label">Dil (içerik)</label>
            <select id="locale" name="locale" class="admin-field mt-1" required>
                @foreach (config('landing.locales') as $code => $label)
                    <option value="{{ $code }}" @selected(old('locale', $category->locale) === $code)>{{ $label }}</option>
                @endforeach
            </select>
            @error('locale')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="slug" class="admin-label">Slug (URL)</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug', $category->slug) }}" required class="admin-field mt-1 font-mono text-sm" />
            @error('slug')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="name" class="admin-label">Görünen ad</label>
            <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" required class="admin-field mt-1" />
            @error('name')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="meta_title" class="admin-label">SEO başlık (isteğe bağlı)</label>
            <input id="meta_title" name="meta_title" type="text" value="{{ old('meta_title', $category->meta_title) }}" class="admin-field mt-1" />
            @error('meta_title')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="meta_description" class="admin-label">SEO açıklama (isteğe bağlı)</label>
            <textarea id="meta_description" name="meta_description" rows="3" class="admin-field mt-1">{{ old('meta_description', $category->meta_description) }}</textarea>
            @error('meta_description')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="sort_order" class="admin-label">Sıra</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="500" value="{{ old('sort_order', $category->sort_order) }}" class="admin-field mt-1 w-28" />
            @error('sort_order')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="admin-btn-emerald-lg">Güncelle</button>
            <a href="{{ route('admin.blog-categories.index') }}" class="admin-btn-outline">Listeye dön</a>
        </div>
    </form>
</x-admin.layout>
