<?php

return [
    /**
     * Boş: yalnızca geçerli lisans anahtarı yeterli (rate limit önerilir).
     * Dolu: POST /api/v1/license/validate için Authorization: Bearer <değer> zorunlu.
     */
    'license_api_secret' => env('HOSTVIM_LICENSE_API_SECRET', ''),

    /**
     * Ödeme: Türkiye → PayTR, diğer → Stripe (locale veya zorlama ile).
     */
    'billing' => [
        /** auto | paytr | stripe — auto iken locale ve force_provider’a bakılır */
        'default_provider' => env('HOSTVIM_BILLING_DEFAULT', 'auto'),
        /** Boş değilse (paytr|stripe) her zaman bu sağlayıcı kullanılır */
        'force_provider' => env('HOSTVIM_BILLING_FORCE_PROVIDER', ''),
        /** default_provider=auto iken bu locale’ler PayTR seçer */
        'turkish_locales' => array_values(array_filter(array_map('trim', explode(',', (string) env('HOSTVIM_BILLING_TR_LOCALES', 'tr'))))),
    ],

    'paytr' => [
        'merchant_id' => env('PAYTR_MERCHANT_ID', ''),
        'merchant_key' => env('PAYTR_MERCHANT_KEY', ''),
        'merchant_salt' => env('PAYTR_MERCHANT_SALT', ''),
        /** 1 = test işlem (canlı mağazada PayTR test modu) */
        'test_mode' => env('PAYTR_TEST_MODE', '0'),
        'debug_on' => env('PAYTR_DEBUG_ON', '0'),
        'timeout_limit' => (int) env('PAYTR_TIMEOUT_MINUTES', 30),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],
];
