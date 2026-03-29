<?php

use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\ServerSetupController;
use App\Http\Controllers\Admin\OutboundMailSettingsController;
use App\Http\Controllers\Admin\TerminalSettingsController;
use App\Http\Controllers\Admin\StackController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BillingController;
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
use App\Http\Controllers\Api\SiteToolsController;
use App\Http\Controllers\Api\SslController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\UiLinksController;
use App\Services\EngineApiService;
use Illuminate\Support\Facades\Route;

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

    Route::get('dashboard', [SystemController::class, 'dashboard']);
    Route::get('config/ui-links', [UiLinksController::class, 'show']);

    Route::apiResource('domains', DomainController::class)->except(['update']);
    Route::post('domains/{domain}/php', [DomainController::class, 'switchPhp']);
    Route::post('domains/{domain}/status', [DomainController::class, 'setStatus']);
    Route::post('domains/{domain}/server', [DomainController::class, 'switchServer']);

    Route::apiResource('databases', DatabaseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('databases/{database}/rotate-password', [DatabaseController::class, 'rotatePassword']);

    Route::prefix('domains/{domain}/files')->group(function () {
        Route::get('/', [FileManagerController::class, 'index']);
        Route::get('search', [FileManagerController::class, 'search']);
        Route::post('mkdir', [FileManagerController::class, 'mkdir']);
        Route::delete('/', [FileManagerController::class, 'destroy']);
        Route::get('read', [FileManagerController::class, 'read']);
        Route::post('write', [FileManagerController::class, 'write']);
        Route::post('upload', [FileManagerController::class, 'upload']);
    });

    Route::get('backups', [BackupController::class, 'index']);
    Route::post('backups', [BackupController::class, 'store']);
    Route::delete('backups/{backup}', [BackupController::class, 'destroy']);
    Route::post('backups/{backup}/restore', [BackupController::class, 'restore']);
    Route::get('backups/engine/snapshot', [BackupController::class, 'engineSnapshot']);

    Route::get('domains/{domain}/ftp', [FtpController::class, 'index']);
    Route::post('domains/{domain}/ftp', [FtpController::class, 'store']);
    Route::delete('ftp/{ftpAccount}', [FtpController::class, 'destroy']);

    Route::get('domains/{domain}/email', [EmailAccountController::class, 'index']);
    Route::post('domains/{domain}/email', [EmailAccountController::class, 'store']);
    Route::patch('email/{emailAccount}', [EmailAccountController::class, 'update']);
    Route::delete('email/{emailAccount}', [EmailAccountController::class, 'destroy']);

    Route::get('domains/{domain}/dns', [DnsRecordController::class, 'index']);
    Route::post('domains/{domain}/dns', [DnsRecordController::class, 'store']);
    Route::delete('dns/{dnsRecord}', [DnsRecordController::class, 'destroy']);

    Route::get('ssl', [SslController::class, 'index']);
    Route::post('domains/{domain}/ssl/issue', [SslController::class, 'issue']);
    Route::post('domains/{domain}/ssl/renew', [SslController::class, 'renew']);
    Route::post('domains/{domain}/ssl/revoke', [SslController::class, 'revoke']);

    Route::get('cron/summary', [CronJobController::class, 'summary']);
    Route::get('cron', [CronJobController::class, 'index']);
    Route::post('cron', [CronJobController::class, 'store']);
    Route::patch('cron/{cronJob}', [CronJobController::class, 'update']);
    Route::delete('cron/{cronJob}', [CronJobController::class, 'destroy']);

    Route::get('monitoring/summary', [MonitoringController::class, 'userSummary']);
    Route::get('monitoring/server', [MonitoringController::class, 'server']);

    Route::get('security/overview', [SecurityController::class, 'overview']);
    Route::post('security/firewall', [SecurityController::class, 'firewall'])->middleware('role:admin');

    Route::get('installer/apps', [InstallerController::class, 'apps']);
    Route::post('domains/{domain}/installer', [InstallerController::class, 'install']);
    Route::post('domains/{domain}/tools', [SiteToolsController::class, 'run']);

    Route::get('license', [LicenseController::class, 'status']);
    Route::post('license/validate', [LicenseController::class, 'validate'])->middleware('role:admin');

    Route::get('billing/packages', [BillingController::class, 'packages']);
    Route::get('billing/subscriptions', [BillingController::class, 'subscriptions']);
    Route::post('billing/checkout', [BillingController::class, 'checkout']);

    Route::post('terminal/session', [TerminalController::class, 'session'])->middleware('role:admin');

    Route::middleware('role:admin')->prefix('system')->group(function () {
        Route::get('stats', [SystemController::class, 'stats']);
        Route::get('services', [SystemController::class, 'services']);
        Route::post('services/{name}', [SystemController::class, 'serviceAction']);
        Route::post('nginx/reload', function (EngineApiService $engine) {
            return response()->json($engine->reloadNginx());
        });
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('server/capabilities', [ServerSetupController::class, 'capabilities']);
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

    Route::middleware('role:reseller')->prefix('reseller')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('packages', [PackageController::class, 'index']);
    });
});

Route::post('billing/webhook', [BillingController::class, 'webhook'])
    ->middleware('throttle:webhooks');

Route::get('health', fn () => response()->json([
    'status' => 'ok',
    'panel' => 'panelsar',
    'version' => config('panelsar.version', '0.1.0'),
]));
