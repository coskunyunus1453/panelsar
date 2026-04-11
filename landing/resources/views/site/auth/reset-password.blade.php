<x-site.layout :title="landing_t('auth.reset_meta_title')" :description="landing_t('auth.reset_meta_description')">
    <div class="hv-container max-w-md py-12">
        <div class="rounded-2xl border border-slate-200/90 bg-white/90 p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/50">
            <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50">{{ landing_t('auth.reset_heading') }}</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ landing_t('auth.reset_lead') }}</p>

            <form method="post" action="{{ route('password.store') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}" />
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ landing_t('auth.label_email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username"
                           @if($email !== '') readonly @endif
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 @if($email !== '') opacity-90 @endif" />
                    @error('email')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ landing_t('auth.label_new_password') }}</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                    @error('password')
                        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ landing_t('auth.label_new_password_confirm') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <button type="submit" class="w-full rounded-xl bg-[rgb(var(--hv-brand-600)/1)] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-95">
                    {{ landing_t('auth.submit_save_password') }}
                </button>
            </form>
        </div>
    </div>
</x-site.layout>
