<?php

use App\Http\Middleware\AuthenticateWhmcsIntegration;
use App\Http\Middleware\EnforceVendorHost;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\RequireTwoFactorForAdmin;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\SetApiLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
        $schedule->command('backups:run-due')->everyMinute();
        $schedule->command('hostvim:self-heal')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Nginx / TLS sonlandırma arkasında doğru şema (wss, secure() vb.)
        $trustedProxies = array_values(array_filter(array_map(
            static fn (string $v) => trim($v),
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1'))
        )));
        $middleware->trustProxies(at: $trustedProxies);
        $middleware->throttleApi();
        $middleware->append(SecureHeaders::class);
        // API cevaplarında (validation/auth) seçili dile göre translateler çalışsın.
        $middleware->append(SetApiLocale::class);
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => EnsureTokenAbility::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'require_admin_2fa' => RequireTwoFactorForAdmin::class,
            'require_password_change' => RequirePasswordChange::class,
            'vendor_host' => EnforceVendorHost::class,
            'whmcs.integration' => AuthenticateWhmcsIntegration::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Yüklenen istek boyutu sunucu limitini aştı.',
                    'hint' => sprintf(
                        'PHP limitlerini kontrol edin: upload_max_filesize=%s, post_max_size=%s',
                        (string) ini_get('upload_max_filesize'),
                        (string) ini_get('post_max_size')
                    ),
                ], 413);
            }

            return null;
        });
    })->create();
