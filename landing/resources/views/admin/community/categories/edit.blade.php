<x-admin.layout title="Kategori düzenle">
    <form method="post" action="{{ route('admin.community.categories.update', $category) }}" class="max-w-xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium">Ad (TR) *</label>
            <input type="text" name="name" value="{{ old('name', $category->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Ad (EN)</label>
            <input type="text" name="name_en" value="{{ old('name_en', $category->name_en) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Slug *</label>
            <input type="text" name="slug" value="{{ old('slug', $category->slug) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Açıklama (TR)</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('description', $category->description) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Açıklama (EN)</label>
            <textarea name="description_en" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('description_en', $category->description_en) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Sıra</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" class="mt-1 w-32 rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0" />
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active)) />
            Aktif
        </label>
        <div>
            <label class="block text-sm font-medium">Meta başlık (TR)</label>
            <input type="text" name="meta_title" value="{{ old('meta_title', $category->meta_title) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Meta başlık (EN)</label>
            <input type="text" name="meta_title_en" value="{{ old('meta_title_en', $category->meta_title_en) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Meta açıklama (TR)</label>
            <textarea name="meta_description" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('meta_description', $category->meta_description) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Meta açıklama (EN)</label>
            <textarea name="meta_description_en" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('meta_description_en', $category->meta_description_en) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Robots</label>
            <select name="robots_override" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <option value="">Varsayılan</option>
                <option value="index" @selected(old('robots_override', $category->robots_override) === 'index')>index</option>
                <option value="noindex" @selected(old('robots_override', $category->robots_override) === 'noindex')>noindex</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="admin-btn-emerald">Güncelle</button>
            <a href="{{ route('admin.community.categories.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">Geri</a>
        </div>
    </form>
</x-admin.layout>
