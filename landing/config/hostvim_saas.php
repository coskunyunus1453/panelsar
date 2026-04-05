<?php

return [
    /**
     * Boş: yalnızca geçerli lisans anahtarı yeterli (rate limit önerilir).
     * Dolu: POST /api/v1/license/validate için Authorization: Bearer <değer> zorunlu.
     */
    'license_api_secret' => env('HOSTVIM_LICENSE_API_SECRET', ''),
];
