<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost,http://localhost:3000,http://127.0.0.1,http://127.0.0.1:3000'))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Origin', 'Content-Type', 'Accept', 'Authorization', 'X-Requested-With', 'X-Vendor-Signature', 'X-Vendor-Timestamp', 'X-Vendor-Nonce'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
