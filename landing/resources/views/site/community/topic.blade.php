@php
    use Illuminate\Support\Str;
@endphp
<x-site.layout
    :title="$seoTitle"
    :description="$seoDescription"
    :canonical-url="$canonicalUrl"
    :robots-content="$robotsContent ?? 'index, follow'"
    :schema-json-ld="$schemaJsonLd ?? null"
>
    <div class="hv-container py-10">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <nav class="min-w-0 text-sm text-slate-600 dark:text-slate-400" aria-label="İçerik yolu">
                <a href="{{ route('community.index') }}" class="hover:text-[rgb(var(--hv-brand-600)/1)]">Topluluk</a>
                <span class="mx-2">/</span>
                @if ($topic->category)
                    <a href="{{ route('community.category', $topic->category->slug) }}" class="hover:text-[rgb(var(--hv-brand-600)/1)]">{{ $topic->category->name }}</a>
                    <span class="mx-2">/</span>
                @endif
                <span class="text-slate-900 dark:text-slate-200">{{ Str::limit($topic->title, 80) }}</span>
            </nav>
            <x-community.ask-cta layout="compact" :category="$topic->category" class="sm:shrink-0" />
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
        @endif

        @if ($topic->moderation_status === \App\Models\CommunityTopic::MODERATION_PENDING)
            <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                Bu konu <strong>moderasyon bekliyor</strong>; onaydan sonra herkese görünür ve arama motorlarında listelenir.
            </div>
        @endif

        <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_min(18rem,100%)] lg:gap-8">
        <article class="rounded-2xl border border-slate-200/90 bg-white/90 p-6 dark:border-slate-800 dark:bg-slate-900/50">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-50">{{ $topic->title }}</h1>
            @if ($topic->tags->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($topic->tags as $tg)
                        <a href="{{ route('community.tag', $tg) }}" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">#{{ $tg->name }}</a>
                    @endforeach
                </div>
            @endif
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                <span class="inline-flex items-center gap-2">
                    <img src="{{ community_user_avatar_url($topic->author, 64) }}" alt="" width="32" height="32" class="h-8 w-8 rounded-full object-cover" loading="lazy" decoding="async" />
                    <span>{{ $topic->author?->name }}</span>
                </span>
                <span>·</span>
                <span>{{ $topic->view_count }} görüntülenme</span>
            </div>
            <div class="prose prose-slate mt-6 max-w-none dark:prose-invert">
                {!! community_rich_display($topic->body) !!}
            </div>
        </article>

        @if (isset($similarTopics) && $similarTopics->isNotEmpty())
            <aside class="mt-8 lg:mt-0">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Benzer konular</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($similarTopics as $st)
                        <li>
                            <a href="{{ route('community.topic', $st) }}" class="font-medium text-[rgb(var(--hv-brand-600)/1)] hover:underline">{{ Str::limit($st->title, 64) }}</a>
                        </li>
                    @endforeach
                </ul>
            </aside>
        @endif
        </div>

        <section class="mt-10">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Yanıtlar</h2>
            <div class="mt-4 space-y-4">
                @foreach ($posts as $post)
                    <div id="cevap-{{ $post->id }}" class="scroll-mt-28 rounded-2xl border p-4 {{ $topic->best_answer_post_id === $post->id ? 'border-emerald-400/80 bg-emerald-50/50 dark:border-emerald-800 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-800' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-2 text-sm font-semibold">
                                <img src="{{ community_user_avatar_url($post->author, 64) }}" alt="" width="28" height="28" class="h-7 w-7 rounded-full object-cover" loading="lazy" decoding="async" />
                                {{ $post->author?->name }}
                            </span>
                            @if ($topic->best_answer_post_id === $post->id)
                                <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-bold text-white">En iyi yanıt</span>
                            @endif
                            @auth
                                @if (auth()->id() === $topic->user_id && ! $topic->is_locked && $topic->best_answer_post_id !== $post->id)
                                    <form method="post" action="{{ route('community.best', $topic) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="post_id" value="{{ $post->id }}" />
                                        <button type="submit" class="text-xs font-semibold text-[rgb(var(--hv-brand-600)/1)] hover:underline">Çözüm olarak işaretle</button>
                                    </form>
                                @endif
                            @endauth
                        </div>
                        @if ($post->moderation_status === \App\Models\CommunityPost::MODERATION_PENDING && auth()->check() && auth()->id() === $post->user_id)
                            <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-400">Bu yanıt moderasyon bekliyor.</p>
                        @endif
                        <div class="prose prose-slate mt-3 max-w-none dark:prose-invert">
                            {!! community_rich_display($post->body) !!}
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @auth
            @if (! $topic->is_locked)
                <section class="mt-10 rounded-2xl border border-slate-200 p-5 dark:border-slate-800">
                    <h3 class="text-sm font-semibold">Yanıt yaz</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Zengin metin editörü; bağlantılar yalnızca güvenli adreslere izin verilir.</p>
                    <form method="post" action="{{ route('community.reply', $topic) }}" class="relative mt-3 space-y-3">
                        @csrf
                        <x-community.honeypot />
                        <div>
                            <x-admin.rich-editor
                                name="body"
                                :value="old('body')"
                                id="community-reply-body"
                                placeholder="Yanıtınızı yazın…"
                                min-height="clamp(11rem, 36vh, 22rem)"
                            />
                            @error('body')
                                <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-4 py-2 text-sm font-semibold text-white">Gönder</button>
                    </form>
                </section>
            @else
                <p class="mt-8 text-sm text-slate-500">Bu konu kilitli.</p>
            @endif
        @else
            <p class="mt-8 text-sm text-slate-600">
                Yanıt yazmak için
                <a href="{{ route('login', ['redirect' => route('community.topic', $topic->slug, absolute: false)]) }}" class="font-semibold text-[rgb(var(--hv-brand-600)/1)] hover:underline">giriş yapın</a>
                veya
                <a href="{{ route('register', ['redirect' => route('community.topic', $topic->slug, absolute: false)]) }}" class="font-semibold text-[rgb(var(--hv-brand-600)/1)] hover:underline">kayıt olun</a>.
            </p>
        @endauth

        <div class="pointer-events-none fixed bottom-5 right-5 z-40 md:hidden">
            <div class="pointer-events-auto">
                <x-community.ask-cta layout="fab" :category="$topic->category" />
            </div>
        </div>
    </div>
</x-site.layout>
