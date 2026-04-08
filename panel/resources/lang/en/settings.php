<?php

return [
    'branding_saved' => 'Branding images saved.',
    'branding_config_saved' => 'Branding upload setting updated.',
    'branding_table_missing' => 'The panel_settings table is missing. On the server run: php artisan migrate --force',
    'branding_storage_not_writable' => 'Cannot create or write to storage/app/public. Check directory permissions and disk space (e.g. chown/chmod for the web user).',
    'branding_upload_failed' => 'Logo upload failed. Check file type (JPEG, PNG, GIF, WebP), size limit, and permissions.',
    'white_label_saved' => 'White-label settings saved.',
    'white_label_slug_taken' => 'This short link slug is already in use.',
    'white_label_hostname_taken' => 'This panel hostname is already registered.',
    'white_label_reseller_only' => 'Only reseller accounts can manage white-label settings.',
];
