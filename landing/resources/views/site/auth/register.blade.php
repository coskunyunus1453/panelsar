<x-site.layout :title="landing_t('auth.register_meta_title')" :description="landing_t('auth.register_meta_description')">
    <div class="hv-container max-w-md py-12">
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/50">
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ landing_t('auth.register_heading') }}</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ landing_t('auth.register_lead') }}</p>

            <form method="post" action="{{ route('register.store') }}" class="relative mt-6 space-y-4">
                @csrf
                <x-community.honeypot />
                @if (! empty($redirect ?? null))
                    <input type="hidden" name="redirect" value="{{ $redirect }}" />
                @endif
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ landing_t('auth.label_full_name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="100" autocomplete="name"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                    @error('name')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ landing_t('auth.label_email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                    @error('email')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ landing_t('auth.label_password') }}</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                    @error('password')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ landing_t('auth.label_password_confirm') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <button type="submit" class="w-full rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-95">
                    {{ landing_t('auth.submit_register') }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
                {{ landing_t('auth.has_account_prompt') }} <a href="{{ route('login', array_filter(['redirect' => $redirect ?? null, 'lang' => $landingLocale ?? app()->getLocale()])) }}" class="font-semibold text-[rgb(var(--hv-brand-600)/1)] hover:underline">{{ landing_t('auth.link_login') }}</a>
            </p>
        </div>
    </div>
</x-site.layout>
