<?php

$panelRoot = dirname(__DIR__);

return [
    'version' => '0.1.0',

    /** Hosting dosya kökü (engine `paths.web_root` ile aynı olmalı; boşsa proje kökü/data/www) */
    'hosting_web_root' => env('PANELSAR_HOSTING_WEB_ROOT', dirname($panelRoot).DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'www'),

    'engine_url' => env('ENGINE_API_URL', 'http://127.0.0.1:9090'),
    'engine_internal_key' => env('ENGINE_INTERNAL_KEY', ''),
    'engine_secret' => env('ENGINE_API_SECRET', ''),

    /** Web terminal WebSocket URL’si (wss) — HTTP panelde kapalı; TLS/Cloudflare sonrası true yapın */
    'force_wss_terminal' => filter_var(env('FORCE_WSS_TERMINAL', false), FILTER_VALIDATE_BOOLEAN),

    /** Let’s Encrypt: istekte e-posta yoksa engine `hosting.lets_encrypt_email` ile birlikte kullanılır */
    'lets_encrypt_email' => env('PANELSAR_LETS_ENCRYPT_EMAIL', ''),

    'license_server' => env('LICENSE_SERVER_URL', 'https://license.panelsar.com'),
    'license_key' => env('LICENSE_KEY', ''),

    'default_locale' => env('PANEL_DEFAULT_LOCALE', 'en'),
    'available_locales' => explode(',', env('PANEL_AVAILABLE_LOCALES', 'en,tr,de,fr,es,pt,zh,ja,ar,ru')),

    'default_php_version' => '8.2',
    'supported_php_versions' => ['7.4', '8.0', '8.1', '8.2', '8.3'],

    'backup' => [
        'retention_days' => 30,
        'max_backups_per_user' => 5,
    ],

    'limits' => [
        'max_upload_size_mb' => 256,
        'max_file_manager_size_mb' => 50,
    ],

    /**
     * Arayüzde “harici araç” bağlantıları (ör. https://mysql.example.com/phpmyadmin).
     */
    'ui' => [
        'phpmyadmin_url' => env('PHPMYADMIN_URL', ''),
        'adminer_url' => env('ADMINER_URL', ''),
    ],

    /** Panel üzerinden gerçek MySQL veritabanı/kullanıcı oluşturma (XAMPP: root + boş şifre) */
    'mysql_provision' => [
        'enabled' => env('MYSQL_PROVISION_ENABLED', false),
        'host' => env('MYSQL_PROVISION_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => (int) env('MYSQL_PROVISION_PORT', env('DB_PORT', 3306)),
        'username' => env('MYSQL_PROVISION_USERNAME', 'root'),
        'password' => env('MYSQL_PROVISION_PASSWORD', ''),
        /** Yeni veritabanı için varsayılan MySQL kullanıcı @host (localhost / 127.0.0.1 / % vb.) */
        'grant_host' => env('MYSQL_PROVISION_GRANT_HOST', 'localhost'),
        /**
         * İzin verilen GRANT host değerleri (özel IP için ayrıca geçerli IPv4/IPv6 kabul edilir).
         *
         * @var list<string>
         */
        'allowed_grant_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MYSQL_ALLOWED_GRANT_HOSTS', 'localhost,127.0.0.1,%'))
        ))),
    ],

    /** PostgreSQL: CREATE DATABASE / USER (pdo_pgsql gerekir) */
    'postgres_provision' => [
        'enabled' => env('POSTGRES_PROVISION_ENABLED', false),
        'host' => env('POSTGRES_PROVISION_HOST', '127.0.0.1'),
        'port' => (int) env('POSTGRES_PROVISION_PORT', 5432),
        'username' => env('POSTGRES_PROVISION_USERNAME', 'postgres'),
        'password' => env('POSTGRES_PROVISION_PASSWORD', ''),
        'admin_database' => env('POSTGRES_PROVISION_ADMIN_DB', 'postgres'),
    ],
];
