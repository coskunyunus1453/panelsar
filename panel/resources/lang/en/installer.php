<?php

return [
    'started' => 'Application installation started',
    'completed' => 'Installation completed.',
    'automated_only_wordpress' => 'Only WordPress can be installed automatically in this version.',
    'wordpress_requires_db' => 'Select a MySQL database for WordPress.',
    'wordpress_mysql_db' => 'Choose a MySQL database that belongs to your account.',
    'engine_unreachable' => 'Panelsar Engine is not reachable at :url. The panel cannot run WordPress install until the engine API is running.',
    'engine_start_hint' => 'Start the engine from the panelsar/engine folder (e.g. go run ./cmd/panelsar-engine with configs/engine.yaml). Set ENGINE_API_URL and ENGINE_INTERNAL_KEY in the panel .env to match engine security.internal_api_key.',
];
