<?php

return [
    'email_required' => 'Let’s Encrypt needs an email address. Set LETS_ENCRYPT_EMAIL / hostvim config or enter one in the SSL form.',

    'invalid_lets_encrypt_email' => 'Let’s Encrypt rejected this contact email (:email). Use a real address whose domain contains a dot (e.g. admin@example.com); update the panel user email, pass `email` in the request, or set HOSTVIM_LETS_ENCRYPT_EMAIL in .env.',
    'issued' => 'SSL certificate issued',
    'renewed' => 'SSL certificate renewed',
    'revoked' => 'SSL certificate revoked',
    'missing' => 'No SSL certificate for this domain',
    'manual_uploaded' => 'Manual SSL certificate uploaded',
];
