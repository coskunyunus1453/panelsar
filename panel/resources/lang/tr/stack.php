<?php

return [
    'install_ok' => 'Paket demeti kuruldu.',
    'mail_test_subject' => 'Hostvim posta testi',
    'mail_test_body' => 'Giden posta ayarları çalışıyor.',
    'mail_test_sent' => 'Test mesajı gönderildi (SMTP veya sendmail): :email. Gelen kutusu ve spam klasörünü kontrol edin.',
    'mail_test_requires_saved_settings' => 'Önce posta ayarlarını Kaydet ile panelde saklayın. Kayıt yokken test, yalnızca .env/log gibi gerçek gönderim yapmayan sürücülere düşebilir.',
    'mail_test_requires_real_transport' => '“Log” veya “:driver” sürücüsü gerçek e-posta göndermez. Test için Sendmail veya SMTP seçip kaydedin.',
    'mail_test_smtp_host_required' => 'SMTP seçiliyken sunucu adresi (host) zorunludur.',
];
