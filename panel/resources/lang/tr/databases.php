<?php

return [
    'created' => 'Veritabanı başarıyla oluşturuldu.',
    'deleted' => 'Veritabanı başarıyla silindi.',
    'updated' => 'Veritabanı başarıyla güncellendi.',
    'password_rotated' => 'Veritabanı şifresi başarıyla yenilendi.',
    'rotate_mysql_only' => 'Şifre döndürme yalnızca MySQL veritabanları için geçerlidir.',
    'rotate_password_unsupported' => 'Bu veritabanı tipi için şifre döndürme yok.',
    'grant_host_mysql_only' => 'Erişim hostu yalnızca MySQL için geçerlidir.',
    'update_no_changes' => 'Güncellenecek en az bir alan seçin.',
    'password_unreadable' => 'Kayıtlı veritabanı şifresi çözülemedi. Yeni bir şifre girin veya «Şifre döndür» ile sıfırlayın.',
    'credentials_sync_reminder' => 'Sunucudaki veritabanı şifresi güncellendi. WordPress (wp-config.php), Laravel (.env) ve diğer uygulamalarda aynı şifreyi elle güncellemeniz gerekir; panel site dosyalarını otomatik değiştirmez.',
    'mysql_user_missing' => 'MySQL’de «:user» kullanıcısı bulunamadı (mysql.user). Kullanıcı silinmiş veya farklı bir sunucuya bağlı olabilir.',
    'mysql_user_host_ambiguous' => '«:user» için birden fazla MySQL Host kaydı var (:hosts). Panel hangi @host ile işlem yapacağını seçemiyor; fazla hesapları MySQL’den kaldırın veya grant_host’u netleştirin.',
    'provision_failed' => 'Veritabanı sunucuda oluşturulamadı (paneldeki MySQL/MariaDB ayarlarını kontrol edin)',

    'export_not_mysql' => 'Dışa aktarma yalnızca MySQL veritabanları için geçerlidir.',
    'export_not_postgresql' => 'Dışa aktarma yalnızca PostgreSQL veritabanları için geçerlidir.',
    'export_unsupported_type' => 'Bu veritabanı tipi için dışa aktarma yok.',
    'export_failed' => 'Dışa aktarma başarısız.',
    'provision_disabled_export' => 'Panel yapılandırmasında veritabanı araçları kapalı; dışa aktarma kullanılamaz.',

    'import_not_mysql' => 'İçe aktarma yalnızca MySQL veritabanları için geçerlidir.',
    'import_not_postgresql' => 'İçe aktarma yalnızca PostgreSQL veritabanları için geçerlidir.',
    'import_unsupported_type' => 'Bu veritabanı tipi için içe aktarma yok.',
    'import_failed' => 'İçe aktarma başarısız.',
    'imported' => 'Yedek başarıyla içe aktarıldı. Önceki veritabanı içeriği tamamen değiştirildi.',
    'provision_disabled_import' => 'Panel yapılandırmasında veritabanı araçları kapalı; içe aktarma kullanılamaz.',
    'import_file_unreadable' => 'Yüklenen dosya okunamadı.',
    'import_sql_only' => 'Yalnızca .sql dosyası kabul edilir.',
    'import_confirm_expected' => 'TÜMVERİSİLİNECEK',
    'import_confirm_mismatch' => 'Onay metni eşleşmiyor. Dil dosyanızdaki ifadeyi aynen yazın.',
];
