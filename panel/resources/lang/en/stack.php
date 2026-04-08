<?php

return [
    'install_ok' => 'Bundle installed successfully.',
    'mail_test_subject' => 'Hostvim mail test',
    'mail_test_body' => 'Outbound mail settings are working.',
    'mail_test_sent' => 'Test message was handed off via SMTP or sendmail to :email. Check inbox and spam.',
    'mail_test_failed_generic' => 'Test email could not be sent. Check SMTP/sendmail settings and logs.',
    'mail_test_requires_saved_settings' => 'Save mail settings in the panel first. Without saved settings, tests may use .env/log drivers that do not deliver real email.',
    'mail_test_requires_real_transport' => 'The “log” or “:driver” driver does not send real email. Choose sendmail or SMTP and save.',
    'mail_test_smtp_host_required' => 'SMTP host is required when using the SMTP driver.',
];
