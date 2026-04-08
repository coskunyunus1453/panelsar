<?php

return [
    'email_required' => 'Let’s Encrypt için geçerli bir e-posta gerekir. .env / hostvim yapılandırmasında LETS_ENCRYPT_EMAIL tanımlayın veya SSL adımında girin.',

    'invalid_lets_encrypt_email' => 'Let’s Encrypt bu iletişim adresini kabul etmiyor (:email). Alan adında nokta olan gerçek bir adres kullanın (örn. yonetim@sirketiniz.com); panel kullanıcı e-postanızı güncelleyin veya istekte `email` alanı gönderin / .env içinde HOSTVIM_LETS_ENCRYPT_EMAIL ayarlayın.',
    'issued' => 'SSL sertifikası oluşturuldu',
    'renewed' => 'SSL sertifikası yenilendi',
    'revoked' => 'SSL sertifikası iptal edildi',
    'missing' => 'Bu alan adı için SSL kaydı yok',
    'manual_uploaded' => 'Manuel SSL sertifikası yüklendi',
];
