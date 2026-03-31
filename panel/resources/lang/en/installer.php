<?php

return [
    'started' => 'Application installation started',
    'started_background' => 'Installation started in the background. It will continue even if you leave this page.',
    'completed_sync' => 'Installation completed (ran synchronously because background worker is not active).',
    'completed' => 'Installation completed.',
    'automated_only_wordpress' => 'Only WordPress can be installed automatically in this version.',
    'wordpress_requires_db' => 'Select a MySQL database for WordPress.',
    'wordpress_mysql_db' => 'Choose a MySQL database that belongs to your account.',
    'engine_unreachable' => 'Panelsar Engine is not reachable at :url. The panel cannot run WordPress install until the engine API is running.',
    'engine_start_hint' => 'Start the engine from the panelsar/engine folder (e.g. go run ./cmd/panelsar-engine with configs/engine.yaml). Set ENGINE_API_URL and ENGINE_INTERNAL_KEY in the panel .env to match engine security.internal_api_key.',
    'db_password_decrypt' => 'The database password cannot be read by the panel (e.g. if APP_KEY changed). Rotate the database password in the panel or create a new database and try again.',
    'unexpected_error' => 'An unexpected error occurred during installation. Check the panel log (storage/logs) on the server.',
];
