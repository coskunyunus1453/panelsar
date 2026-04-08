<x-site.layout
    :title="$seoTitle"
    :description="$seoDescription"
    :canonical-url="$canonicalUrl"
    :robots-content="$robotsContent"
>
    <div class="hv-container max-w-lg py-10">
        <nav class="mb-6 text-sm text-slate-600 dark:text-slate-400" aria-label="İçerik yolu">
            <a href="{{ route('community.index') }}" class="hover:text-[rgb(var(--hv-brand-600)/1)]">{{ $site->site_title }}</a>
            <span class="mx-2">/</span>
            <span class="text-slate-900 dark:text-slate-200">Profil</span>
        </nav>

        <div class="flex items-center gap-4">
            <img src="{{ community_user_avatar_url(auth()->user(), 96) }}" alt="" width="96" height="96" class="h-16 w-16 shrink-0 rounded-full object-cover ring-2 ring-slate-200 dark:ring-slate-700" loading="lazy" decoding="async" />
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-slate-50">Topluluk profili</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Görünen ad ve isteğe bağlı profil görseli (yalnızca HTTPS adresi).</p>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
        @endif

        <form method="post" action="{{ route('community.profile.update') }}" class="mt-8 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Görünen ad</label>
                <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="100" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-900" />
                @error('name')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Profil görseli URL</label>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Boş bırakırsanız Gravatar kullanılır. Örnek: güvenilir bir CDN veya <span class="font-mono">https://</span> ile başlayan doğrudan resim bağlantısı.</p>
                <input type="url" name="avatar_url" value="{{ old('avatar_url', auth()->user()->avatar_url) }}" placeholder="https://…" maxlength="512" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-600 dark:bg-slate-900" />
                @error('avatar_url')
                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-5 py-2.5 text-sm font-semibold text-white">Kaydet</button>
                <a href="{{ route('community.index') }}" class="self-center text-sm font-medium text-slate-600 underline dark:text-slate-400">Topluluğa dön</a>
            </div>
        </form>
    </div>
</x-site.layout>
