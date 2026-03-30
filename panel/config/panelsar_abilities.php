<?php

/**
 * Panel özellik yetenekleri (Sanctum token + Spatie Permission adı = aynı string).
 * Yeni özellik eklerken burayı güncelleyin; ardından `php artisan panelsar:sync-abilities` çalıştırın.
 *
 * @var list<array{name: string, group: string}>
 */
return [
    ['name' => 'dashboard:read', 'group' => 'core'],
    ['name' => 'sites:read', 'group' => 'sites'],
    ['name' => 'sites:write', 'group' => 'sites'],
    ['name' => 'domains:read', 'group' => 'domains'],
    ['name' => 'domains:write', 'group' => 'domains'],
    ['name' => 'databases:read', 'group' => 'databases'],
    ['name' => 'databases:write', 'group' => 'databases'],
    ['name' => 'email:read', 'group' => 'email'],
    ['name' => 'email:write', 'group' => 'email'],
    ['name' => 'ftp:read', 'group' => 'ftp'],
    ['name' => 'ftp:write', 'group' => 'ftp'],
    ['name' => 'files:read', 'group' => 'files'],
    ['name' => 'files:write', 'group' => 'files'],
    ['name' => 'dns:read', 'group' => 'dns'],
    ['name' => 'dns:write', 'group' => 'dns'],
    ['name' => 'ssl:read', 'group' => 'ssl'],
    ['name' => 'ssl:write', 'group' => 'ssl'],
    ['name' => 'backups:read', 'group' => 'backups'],
    ['name' => 'backups:write', 'group' => 'backups'],
    ['name' => 'cron:read', 'group' => 'cron'],
    ['name' => 'cron:write', 'group' => 'cron'],
    ['name' => 'monitoring:read', 'group' => 'monitoring'],
    ['name' => 'monitoring:server', 'group' => 'monitoring'],
    ['name' => 'security:read', 'group' => 'security'],
    ['name' => 'security:write', 'group' => 'security'],
    ['name' => 'installer:read', 'group' => 'installer'],
    ['name' => 'installer:write', 'group' => 'installer'],
    ['name' => 'tools:run', 'group' => 'tools'],
    ['name' => 'billing:read', 'group' => 'billing'],
    ['name' => 'billing:write', 'group' => 'billing'],
    ['name' => 'webserver:read', 'group' => 'webserver'],
    ['name' => 'webserver:write', 'group' => 'webserver'],
    ['name' => 'php:read', 'group' => 'php'],
    ['name' => 'php:write', 'group' => 'php'],
    ['name' => 'reseller:users', 'group' => 'reseller'],
    ['name' => 'reseller:packages', 'group' => 'reseller'],
    ['name' => 'reseller:roles', 'group' => 'reseller'],
];
