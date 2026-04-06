<x-admin.layout title="Odeme Yontemleri">
    <form method="POST" action="{{ route('admin.billing-settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <h2 class="text-base font-semibold">Genel yonetim</h2>
            <p class="mt-1 text-xs text-slate-500">PayTR, Stripe ve Havale/EFT secenekleri tek yerden aktif edilir.</p>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <label class="rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                    <input type="checkbox" name="paytr_enabled" value="1" class="mr-2" @checked(old('paytr_enabled', $paytrEnabled))>
                    PayTR aktif
                </label>
                <label class="rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                    <input type="checkbox" name="stripe_enabled" value="1" class="mr-2" @checked(old('stripe_enabled', $stripeEnabled))>
                    Stripe aktif
                </label>
                <label class="rounded-xl border border-slate-200 px-4 py-3 text-sm dark:border-slate-700">
                    <input type="checkbox" name="bank_transfer_enabled" value="1" class="mr-2" @checked(old('bank_transfer_enabled', $bankTransferEnabled))>
                    Havale / EFT aktif
                </label>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium">Varsayilan saglayici</label>
                    <select name="default_provider" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        @foreach (['auto' => 'Auto', 'paytr' => 'PayTR', 'stripe' => 'Stripe', 'bank_transfer' => 'Havale / EFT'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('default_provider', $defaultProvider) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Saglayiciyi zorla</label>
                    <select name="force_provider" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        <option value="">Yok</option>
                        @foreach (['paytr' => 'PayTR', 'stripe' => 'Stripe', 'bank_transfer' => 'Havale / EFT'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('force_provider', $forceProvider) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">PayTR locale listesi</label>
                    <input type="text" name="tr_locales" value="{{ old('tr_locales', $trLocales) }}" placeholder="tr,tr-TR" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <h2 class="text-base font-semibold">PayTR ayarlari</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium">Merchant ID</label>
                    <input type="text" name="paytr_merchant_id" value="{{ old('paytr_merchant_id', $paytrMerchantId) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
                <div>
                    <label class="block text-sm font-medium">Merchant Key</label>
                    <input type="text" name="paytr_merchant_key" value="{{ old('paytr_merchant_key', $paytrMerchantKey) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
                <div>
                    <label class="block text-sm font-medium">Merchant Salt</label>
                    <input type="text" name="paytr_merchant_salt" value="{{ old('paytr_merchant_salt', $paytrMerchantSalt) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium">Test modu</label>
                        <select name="paytr_test_mode" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                            <option value="0" @selected(old('paytr_test_mode', $paytrTestMode) === '0')>0</option>
                            <option value="1" @selected(old('paytr_test_mode', $paytrTestMode) === '1')>1</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Debug</label>
                        <select name="paytr_debug_on" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                            <option value="0" @selected(old('paytr_debug_on', $paytrDebugOn) === '0')>0</option>
                            <option value="1" @selected(old('paytr_debug_on', $paytrDebugOn) === '1')>1</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Timeout (dk)</label>
                        <input type="number" min="1" max="120" name="paytr_timeout_minutes" value="{{ old('paytr_timeout_minutes', $paytrTimeoutMinutes) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <h2 class="text-base font-semibold">Stripe ayarlari</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium">Secret key</label>
                    <input type="text" name="stripe_secret" value="{{ old('stripe_secret', $stripeSecret) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
                <div>
                    <label class="block text-sm font-medium">Webhook secret</label>
                    <input type="text" name="stripe_webhook_secret" value="{{ old('stripe_webhook_secret', $stripeWebhookSecret) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <h2 class="text-base font-semibold">Havale / EFT ayarlari</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium">Hesap sahibi</label>
                    <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $bankAccountName) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
                <div>
                    <label class="block text-sm font-medium">Banka adi</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $bankName) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
                <div>
                    <label class="block text-sm font-medium">Sube</label>
                    <input type="text" name="bank_branch" value="{{ old('bank_branch', $bankBranch) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
                <div>
                    <label class="block text-sm font-medium">IBAN</label>
                    <input type="text" name="bank_iban" value="{{ old('bank_iban', $bankIban) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium">Odeme talimati</label>
                <textarea name="bank_instructions" rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-900">{{ old('bank_instructions', $bankInstructions) }}</textarea>
            </div>
        </section>

        <button type="submit" class="admin-btn-emerald">Kaydet</button>
    </form>
</x-admin.layout>
