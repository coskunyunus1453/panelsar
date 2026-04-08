<x-admin.layout title="Konu moderasyonu">
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
    @endif

    @if ($topic->author)
        <div class="mb-6 rounded-2xl border border-slate-200/90 bg-white/90 p-4 dark:border-slate-700 dark:bg-slate-900/60">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Konuyu açan</div>
            <div class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ $topic->author->name }}</div>
            <div class="mt-0.5 font-mono text-sm text-slate-600 dark:text-slate-400">{{ $topic->author->email }}</div>
            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                @if (! $topic->author->is_admin)
                    <a href="{{ route('admin.community.members.edit', $topic->author) }}" class="admin-link-emerald">Topluluk üyesi — düzenle / yasakla</a>
                @else
                    <span class="text-slate-500">Yönetici hesabı</span>
                @endif
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('admin.community.topics.update', $topic) }}" class="mb-10 max-w-3xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium">Başlık</label>
            <input type="text" name="title" value="{{ old('title', $topic->title) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Slug</label>
            <input type="text" name="slug" value="{{ old('slug', $topic->slug) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">İçerik</label>
            <x-admin.rich-editor
                name="body"
                :value="old('body', $topic->body)"
                id="admin-topic-body"
                min-height="min(28rem, 70vh)"
            />
        </div>
        <div>
            <label class="block text-sm font-medium">Durum</label>
            <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <option value="published" @selected(old('status', $topic->status) === 'published')>published</option>
                <option value="hidden" @selected(old('status', $topic->status) === 'hidden')>hidden</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Moderasyon</label>
            <select name="moderation_status" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <option value="approved" @selected(old('moderation_status', $topic->moderation_status ?? 'approved') === 'approved')>approved</option>
                <option value="pending" @selected(old('moderation_status', $topic->moderation_status ?? 'approved') === 'pending')>pending</option>
                <option value="rejected" @selected(old('moderation_status', $topic->moderation_status ?? 'approved') === 'rejected')>rejected</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">Etiketler (virgülle)</label>
            <input type="text" name="tags_line" value="{{ old('tags_line', $topic->tags->pluck('name')->implode(', ')) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_locked" value="0" /><input type="checkbox" name="is_locked" value="1" @checked(old('is_locked', $topic->is_locked)) /> Kilitli</label>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_pinned" value="0" /><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $topic->is_pinned)) /> Sabit</label>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_solved" value="0" /><input type="checkbox" name="is_solved" value="1" @checked(old('is_solved', $topic->is_solved)) /> Çözüldü</label>
        <div>
            <label class="block text-sm font-medium">Meta başlık</label>
            <input type="text" name="meta_title" value="{{ old('meta_title', $topic->meta_title) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Meta açıklama</label>
            <textarea name="meta_description" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('meta_description', $topic->meta_description) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">Canonical URL</label>
            <input type="url" name="canonical_url" value="{{ old('canonical_url', $topic->canonical_url) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900" />
        </div>
        <div>
            <label class="block text-sm font-medium">Robots</label>
            <select name="robots_override" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                <option value="">Varsayılan</option>
                <option value="index" @selected(old('robots_override', $topic->robots_override) === 'index')>index</option>
                <option value="noindex" @selected(old('robots_override', $topic->robots_override) === 'noindex')>noindex</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="admin-btn-emerald">Kaydet</button>
            <a href="{{ route('admin.community.topics.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm dark:border-slate-600">Listeye dön</a>
        </div>
    </form>

    <h2 class="text-lg font-semibold">Yanıtlar</h2>
    <div class="mt-4 space-y-6">
        @foreach ($topic->posts as $post)
            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span>{{ $post->author?->name }} · #{{ $post->id }}</span>
                    <form action="{{ route('admin.community.posts.destroy', $post) }}" method="post" class="inline" onsubmit="return confirm('Yanıt silinsin mi?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-600 dark:text-rose-400">Sil</button>
                    </form>
                </div>
                <form method="post" action="{{ route('admin.community.posts.update', $post) }}" class="mt-2 space-y-2">
                    @csrf
                    @method('PATCH')
                    <x-admin.rich-editor
                        name="body"
                        :value="old('body', $post->body)"
                        :id="'admin-community-post-'.$post->id"
                        min-height="200px"
                    />
                    <label class="flex items-center gap-2 text-xs">
                        <input type="hidden" name="is_hidden" value="0" />
                        <input type="checkbox" name="is_hidden" value="1" @checked(old('is_hidden_'.$post->id, $post->is_hidden)) />
                        Gizli (moderasyon)
                    </label>
                    <div>
                        <label class="text-xs font-medium text-slate-600 dark:text-slate-400">Yanıt moderasyonu</label>
                        <select name="moderation_status" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900">
                            <option value="approved" @selected(old('moderation_status', $post->moderation_status ?? 'approved') === 'approved')>approved</option>
                            <option value="pending" @selected(old('moderation_status', $post->moderation_status ?? 'approved') === 'pending')>pending</option>
                            <option value="rejected" @selected(old('moderation_status', $post->moderation_status ?? 'approved') === 'rejected')>rejected</option>
                        </select>
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs text-white dark:bg-slate-200 dark:text-slate-900">Yanıtı güncelle</button>
                </form>
            </div>
        @endforeach
    </div>

    <form method="post" action="{{ route('admin.community.topics.destroy', $topic) }}" class="mt-10" onsubmit="return confirm('Konu ve tüm yanıtlar silinsin mi?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-rose-600 dark:text-rose-400">Konuyu tamamen sil</button>
    </form>
</x-admin.layout>
