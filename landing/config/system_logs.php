<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Satır ve Boyut Sınırları
    |--------------------------------------------------------------------------
    */
    'max_lines_per_file' => (int) env('SYSTEM_LOGS_MAX_LINES_PER_FILE', 180),
    'max_bytes_per_file' => (int) env('SYSTEM_LOGS_MAX_BYTES_PER_FILE', 1_500_000),

    /*
    |--------------------------------------------------------------------------
    | Sabit ve Güvenli Log Kaynakları
    |--------------------------------------------------------------------------
    | Dışarıdan path alımı yoktur (path traversal engeli).
    */
    'sources' => [
        'laravel' => [
            'label' => 'Laravel',
            'files' => [
                storage_path('logs/laravel.log'),
            ],
            'globs' => [
                storage_path('logs/laravel-*.log'),
            ],
        ],
        'php' => [
            'label' => 'PHP',
            'files' => [
                '/var/log/php-fpm.log',
                '/var/log/php8.2-fpm.log',
                '/var/log/php8.1-fpm.log',
                '/var/log/php8.0-fpm.log',
                '/var/log/php7.4-fpm.log',
                '/var/log/php_errors.log',
            ],
            'globs' => [],
        ],
        'nginx' => [
            'label' => 'Nginx',
            'files' => [
                '/var/log/nginx/error.log',
                '/var/log/nginx/access.log',
            ],
            'globs' => [],
        ],
        'apache' => [
            'label' => 'Apache',
            'files' => [
                '/var/log/apache2/error.log',
                '/var/log/apache2/access.log',
                '/usr/local/apache/logs/error_log',
                '/usr/local/apache/logs/access_log',
            ],
            'globs' => [],
        ],
        'openlitespeed' => [
            'label' => 'OpenLiteSpeed',
            'files' => [
                '/usr/local/lsws/logs/error.log',
                '/usr/local/lsws/logs/access.log',
            ],
            'globs' => [],
        ],
        'system' => [
            'label' => 'Sistem',
            'files' => [
                '/var/log/syslog',
                '/var/log/messages',
            ],
            'globs' => [],
        ],
    ],
];
