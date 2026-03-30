<?php

use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\OutboundMailSettingsController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TerminalSettingsController;
use App\Http\Controllers\Admin\StackController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PhpSettingsController;
use App\Http\Controllers\Admin\WebServerSettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CronJobController;
use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\DnsRecordController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\EmailAccountController;
use App\Http\Controllers\Api\FileManagerController;
use App\Http\Controllers\Api\FtpController;
use App\Http\Controllers\Api\InstallerController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\SiteToolsController;
use App\Http\Controllers\Api\SslController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\UiLinksController;
use App\Http\Controllers\Reseller\ResellerRoleController;
use App\Services\EngineApiService;
use Illuminate\Support\Facades\Route;

Route::get('branding', [BrandingController::class, 'showPublic']);
Route::get('branding/files/{filename}', [BrandingController::class, 'serveFile'])
    ->where('filename', '[A-Za-z0-9._-]+');

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'abilities:access:customer-panel'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

Route::middleware(['auth:sanctum', 'abilities:access:customer-panel'])->group(function () {
    Route::patch('user/profile', [ProfileController::class, 'update']);
    Route::post('user/password', [ProfileController::class, 'password']);

    Route::middleware('ability:dashboard:read')->group(function () {
        Route::get('dashboard', [SystemController::class, 'dashboard']);
        Route::get('config/ui-links', [UiLinksController::class, 'show']);
        Route::get('license', [LicenseController::class, 'status']);
    });

    Route::middleware('ability:sites:read')->group(function () {
        Route::get('sites/list', [SiteController::class, 'list']);
    });
    Route::middleware('ability:sites:write')->group(function () {
        Route::post('sites/create', [SiteController::class, 'create']);
        Route::post('sites/delete', [SiteController::class, 'delete']);
        Route::post('sites/subdomain/add', [SiteController::class, 'addSubdomain']);
        Route::post('sites/subdomain/remove', [SiteController::class, 'removeSubdomain']);
        Route::post('sites/domain-alias/add', [SiteController::class, 'addDomainAlias']);
        Route::post('sites/domain-alias/remove', [SiteController::class, 'removeDomainAlias']);
    });

    Route::middleware('ability:domains:read')->group(function () {
        Route::get('domains', [DomainController::class, 'index']);
        Route::get('domains/{domain}', [DomainController::class, 'show']);
    });
    Route::middleware('ability:domains:write')->group(function () {
        Route::post('domains', [DomainController::class, 'store']);
        Route::delete('domains/{domain}', [DomainController::class, 'destroy']);
        Route::post('domains/{domain}/php', [DomainController::class, 'switchPhp']);
        Route::post('domains/{domain}/status', [DomainController::class, 'setStatus']);
        Route::post('domains/{domain}/server', [DomainController::class, 'switchServer']);
    });

    Route::middleware('ability:databases:read')->get('databases', [DatabaseController::class, 'index']);
    Route::middleware('ability:databases:write')->group(function () {
        Route::post('databases', [DatabaseController::class, 'store']);
        Route::patch('databases/{database}', [DatabaseController::class, 'update']);
        Route::delete('databases/{database}', [DatabaseController::class, 'destroy']);
        Route::post('databases/{database}/rotate-password', [DatabaseController::class, 'rotatePassword']);
    });

    Route::prefix('domains/{domain}/files')->group(function () {
        Route::middleware('ability:files:read')->group(function () {
            Route::get('/', [FileManagerController::class, 'index'])->middleware('throttle:files-read');
            Route::get('search', [FileManagerController::class, 'search'])->middleware('throttle:files-read');
            Route::get('read', [FileManagerController::class, 'read'])->middleware('throttle:files-read');
            Route::post('read', [FileManagerController::class, 'read'])->middleware('throttle:files-read');
            Route::get('download', [FileManagerController::class, 'download'])->middleware('throttle:files-read');
        });
        Route::middleware('ability:files:write')->group(function () {
            Route::post('mkdir', [FileManagerController::class, 'mkdir'])->middleware('throttle:files-write');
            Route::delete('/', [FileManagerController::class, 'destroy'])->middleware('throttle:files-write');
            Route::post('write', [FileManagerController::class, 'write'])->middleware('throttle:files-write');
            Route::post('create', [FileManagerController::class, 'create'])->middleware('throttle:files-write');
            Route::post('upload', [FileManagerController::class, 'upload'])->middleware('throttle:files-upload');
            Route::post('rename', [FileManagerController::class, 'rename'])->middleware('throttle:files-write');
            Route::post('move', [FileManagerController::class, 'move'])->middleware('throttle:files-write');
        });
    });

    Route::middleware('ability:backups:read')->group(function () {
        Route::get('backups', [BackupController::class, 'index']);
        Route::get('backups/engine/snapshot', [BackupController::class, 'engineSnapshot']);
    });
    Route::middleware('ability:backups:write')->group(function () {
        Route::post('backups', [BackupController::class, 'store']);
        Route::delete('backups/{backup}', [BackupController::class, 'destroy']);
        Route::post('backups/{backup}/restore', [BackupController::class, 'restore']);
    });

    Route::middleware('ability:ftp:read')->get('domains/{domain}/ftp', [FtpController::class, 'index']);
    Route::middleware('ability:ftp:write')->group(function () {
        Route::post('domains/{domain}/ftp', [FtpController::class, 'store']);
        Route::delete('ftp/{ftpAccount}', [FtpController::class, 'destroy']);
    });

    Route::middleware('ability:email:read')->get('domains/{domain}/email', [EmailAccountController::class, 'index']);
    Route::middleware('ability:email:write')->group(function () {
        Route::post('domains/{domain}/email', [EmailAccountController::class, 'store']);
        Route::patch('email/{emailAccount}', [EmailAccountController::class, 'update']);
        Route::delete('email/{emailAccount}', [EmailAccountController::class, 'destroy']);
    });

    Route::middleware('ability:dns:read')->get('domains/{domain}/dns', [DnsRecordController::class, 'index']);
    Route::middleware('ability:dns:write')->group(function () {
        Route::post('domains/{domain}/dns', [DnsRecordController::class, 'store']);
        Route::delete('dns/{dnsRecord}', [DnsRecordController::class, 'destroy']);
    });

    Route::middleware('ability:ssl:read')->get('ssl', [SslController::class, 'index']);
    Route::middleware('ability:ssl:write')->group(function () {
        Route::post('domains/{domain}/ssl/issue', [SslController::class, 'issue']);
        Route::post('domains/{domain}/ssl/renew', [SslController::class, 'renew']);
        Route::post('domains/{domain}/ssl/revoke', [SslController::class, 'revoke']);
    });

    Route::middleware('ability:cron:read')->group(function () {
        Route::get('cron/summary', [CronJobController::class, 'summary']);
        Route::get('cron', [CronJobController::class, 'index']);
    });
    Route::middleware('ability:cron:write')->group(function () {
        Route::post('cron', [CronJobController::class, 'store']);
        Route::patch('cron/{cronJob}', [CronJobController::class, 'update']);
        Route::delete('cron/{cronJob}', [CronJobController::class, 'destroy']);
    });

    Route::middleware('ability:monitoring:read')->get('monitoring/summary', [MonitoringController::class, 'userSummary']);
    Route::middleware('ability:monitoring:server')->get('monitoring/server', [MonitoringController::class, 'server']);

    Route::middleware('ability:security:read')->get('security/overview', [SecurityController::class, 'overview']);
    Route::middleware(['role:admin', 'ability:security:write'])->post('security/firewall', [SecurityController::class, 'firewall']);

    Route::middleware('ability:installer:read')->get('installer/apps', [InstallerController::class, 'apps']);
    Route::middleware('ability:installer:write')->post('domains/{domain}/installer', [InstallerController::class, 'install']);

    Route::middleware('ability:tools:run')->post('domains/{domain}/tools', [SiteToolsController::class, 'run']);

    Route::middleware('role:admin')->post('license/validate', [LicenseController::class, 'validateWithKey']);

    Route::middleware('ability:billing:read')->group(function () {
        Route::get('billing/packages', [BillingController::class, 'packages']);
        Route::get('billing/subscriptions', [BillingController::class, 'subscriptions']);
    });
    Route::middleware('ability:billing:write')->post('billing/checkout', [BillingController::class, 'checkout']);

    Route::middleware('role:admin')->post('terminal/session', [TerminalController::class, 'session']);

    Route::middleware('role:admin')->prefix('system')->group(function () {
        Route::get('stats', [SystemController::class, 'stats']);
        Route::get('services', [SystemController::class, 'services']);
        Route::post('services/{name}', [SystemController::class, 'serviceAction']);
        Route::post('reboot', [SystemController::class, 'reboot']);
        Route::post('nginx/reload', function (EngineApiService $engine) {
            return response()->json($engine->reloadNginx());
        });
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::post('settings/branding', [BrandingController::class, 'update']);
        Route::get('abilities/registry', [RoleController::class, 'registry']);
        Route::apiResource('roles', RoleController::class)->except(['show']);
        Route::get('stack/modules', [StackController::class, 'modules']);
        Route::post('stack/install', [StackController::class, 'install']);
        Route::get('settings/mail', [OutboundMailSettingsController::class, 'show']);
        Route::put('settings/mail', [OutboundMailSettingsController::class, 'update']);
        Route::post('settings/mail/test', [OutboundMailSettingsController::class, 'test']);
        Route::get('settings/terminal', [TerminalSettingsController::class, 'show']);
        Route::put('settings/terminal', [TerminalSettingsController::class, 'update']);
        Route::apiResource('users', UserController::class);
        Route::post('users/{user}/suspend', [UserController::class, 'suspend']);
        Route::post('users/{user}/activate', [UserController::class, 'activate']);
        Route::apiResource('packages', PackageController::class);
    });

    Route::prefix('admin')->middleware('ability:webserver:read')->group(function () {
        Route::get('settings/webserver', [WebServerSettingsController::class, 'show']);
    });

    Route::prefix('admin')->middleware('ability:webserver:write')->group(function () {
        Route::put('settings/webserver', [WebServerSettingsController::class, 'update']);
    });

    Route::prefix('admin')->middleware('ability:php:read')->group(function () {
        Route::get('settings/php/versions', [PhpSettingsController::class, 'versions']);
        Route::get('settings/php/{version}/ini', [PhpSettingsController::class, 'ini']);
        Route::get('settings/php/{version}/modules', [PhpSettingsController::class, 'modules']);
    });

    Route::prefix('admin')->middleware('ability:php:write')->group(function () {
        Route::put('settings/php/{version}/ini', [PhpSettingsController::class, 'updateIni']);
        Route::patch('settings/php/{version}/modules', [PhpSettingsController::class, 'updateModules']);
    });

    Route::middleware('role:reseller')->prefix('reseller')->group(function () {
        Route::middleware('ability:reseller:users')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
        });
        Route::middleware('ability:reseller:packages')->get('packages', [PackageController::class, 'index']);
        Route::middleware('ability:reseller:roles')->group(function () {
            Route::get('abilities/registry', [ResellerRoleController::class, 'abilityRegistry']);
            Route::get('roles', [ResellerRoleController::class, 'index']);
            Route::post('roles', [ResellerRoleController::class, 'store']);
            Route::put('roles/{role}', [ResellerRoleController::class, 'update']);
            Route::delete('roles/{role}', [ResellerRoleController::class, 'destroy']);
        });
    });
});

Route::post('billing/webhook', [BillingController::class, 'webhook'])
    ->middleware('throttle:webhooks');

Route::get('health', fn () => response()->json([
    'status' => 'ok',
    'panel' => 'panelsar',
    'version' => config('panelsar.version', '0.1.0'),
]));
