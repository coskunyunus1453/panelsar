<?php

return [
    'started' => 'Application installation started',
    'started_background' => 'Installation started in the background. It will continue even if you leave this page.',
    'completed_sync' => 'Installation completed (ran synchronously because background worker is not active).',
    'completed' => 'Installation completed.',
    'automated_only_wordpress' => 'Only WordPress can be installed automatically in this version.',
    'automated_apps_only' => 'From this screen only WordPress (optional WooCommerce) and OpenCart can be installed in one click. For Node, Laravel, Docker, Git deploy and the modern stack, use the guide links or Deploy / Site tools.',
    'wordpress_requires_db' => 'Select a MySQL database for WordPress.',
    'opencart_requires_db' => 'Select a MySQL database for OpenCart (files and a DB hint file are written; you enter the password in the web installer).',
    'wordpress_mysql_db' => 'Choose a MySQL database that belongs to your account.',
    'engine_unreachable' => 'Hostvim Engine is not reachable at :url. The panel cannot run WordPress install until the engine API is running.',
    'engine_start_hint' => 'Start the engine from the engine folder (e.g. go run ./cmd/hostvim-engine with configs/engine.yaml). Set ENGINE_API_URL and ENGINE_INTERNAL_KEY in the panel .env to match engine security.internal_api_key.',
    'db_password_decrypt' => 'The database password cannot be read by the panel (e.g. if APP_KEY changed). Rotate the database password in the panel or create a new database and try again.',
    'unexpected_error' => 'An unexpected error occurred during installation. Check the panel log (storage/logs) on the server.',
];
