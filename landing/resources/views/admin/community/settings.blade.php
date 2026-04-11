<x-admin.layout title="Topluluk — SEO">
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
    @endif

    <form method="post" action="{{ route('admin.community.settings.update') }}" class="max-w-2xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium">Site başlığı (topluluk) — TR</label>
            <input type="text" name="site_title" value="{{ old('site_title', $meta->site_title) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Site başlığı — EN (İngilizce arayüz, boşsa “Community” çevirisi)</label>
            <input type="text" name="site_title_en" value="{{ old('site_title_en', $meta->site_title_en) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" placeholder="Community" />
        </div>
        <div>
            <label class="block text-sm font-medium">Varsayılan meta başlık — TR</label>
            <input type="text" name="default_meta_title" value="{{ old('default_meta_title', $meta->default_meta_title) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Varsayılan meta başlık — EN</label>
            <input type="text" name="default_meta_title_en" value="{{ old('default_meta_title_en', $meta->default_meta_title_en) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Varsayılan meta açıklama — TR</label>
            <textarea name="default_meta_description" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('default_meta_description', $meta->default_meta_description) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Varsayılan meta açıklama — EN</label>
            <textarea name="default_meta_description_en" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('default_meta_description_en', $meta->default_meta_description_en) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">OG görsel URL</label>
            <input type="url" name="og_image_url" value="{{ old('og_image_url', $meta->og_image_url) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Twitter @site</label>
            <input type="text" name="twitter_site" value="{{ old('twitter_site', $meta->twitter_site) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="enable_indexing" value="0" />
            <input type="checkbox" name="enable_indexing" value="1" @checked(old('enable_indexing', $meta->enable_indexing)) />
            Arama motorları indeksleyebilsin (sitemap ile uyumlu)
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="moderation_new_topics" value="0" />
            <input type="checkbox" name="moderation_new_topics" value="1" @checked(old('moderation_new_topics', $meta->moderation_new_topics ?? false)) />
            Tüm yeni konular moderasyon kuyruğuna düşsün
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="moderation_new_posts" value="0" />
            <input type="checkbox" name="moderation_new_posts" value="1" @checked(old('moderation_new_posts', $meta->moderation_new_posts ?? false)) />
            Tüm yeni yanıtlar moderasyon kuyruğuna düşsün
        </label>
        <p class="text-xs text-slate-500">Site haritası: <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">{{ url('/sitemap.xml') }}</code></p>
        <button type="submit" class="admin-btn-emerald">Kaydet</button>
    </form>
</x-admin.layout>
