<?php

return [
    'started' => 'Kurulum başlatıldı',
    'started_background' => 'Kurulum arka planda başlatıldı. Bu sayfayı kapatsanız da devam eder.',
    'completed_sync' => 'Kurulum tamamlandı (arka plan worker kapalı olduğu için eşzamanlı çalıştı).',
    'completed' => 'Kurulum tamamlandı.',
    'automated_only_wordpress' => 'Bu sürümde yalnızca WordPress otomatik kurulur.',
    'wordpress_requires_db' => 'WordPress için bir MySQL veritabanı seçin.',
    'wordpress_mysql_db' => 'Hesabınıza ait bir MySQL veritabanı seçin.',
    'engine_unreachable' => 'Hostvim Engine şu adreste yanıt vermiyor: :url. Engine API çalışmadan WordPress kurulumu yapılamaz.',
    'engine_start_hint' => 'engine dizininde engine’i başlatın (ör. configs/engine.yaml ile go run ./cmd/hostvim-engine). Panel .env içinde ENGINE_API_URL ve ENGINE_INTERNAL_KEY değerlerini engine security.internal_api_key ile eşleştirin.',
    'db_password_decrypt' => 'Veritabanı şifresi panelde okunamıyor (ör. APP_KEY değiştiyse). Veritabanı kaydında şifreyi yenileyin veya yeni bir veritabanı oluşturup tekrar deneyin.',
    'unexpected_error' => 'Kurulum sırasında beklenmeyen bir hata oluştu. Sunucu panel günlüğünü (storage/logs) kontrol edin.',
];
