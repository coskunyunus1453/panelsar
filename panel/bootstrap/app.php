<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use App\Http\Middleware\EnsureTokenAbility;

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
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Nginx / TLS sonlandırma arkasında doğru şema (wss, secure() vb.)
        $trustedProxies = array_values(array_filter(array_map(
            static fn (string $v) => trim($v),
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1'))
        )));
        $middleware->trustProxies(at: $trustedProxies);
        $middleware->throttleApi();
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => EnsureTokenAbility::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
