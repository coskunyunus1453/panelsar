<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\User;
use App\Services\EngineApiService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function __construct(
        private EngineApiService $engineApi,
    ) {}

    public function stats(): JsonResponse
    {
        $stats = $this->engineApi->getSystemStats();

        return response()->json(['stats' => $stats]);
    }

    public function services(): JsonResponse
    {
        $services = $this->engineApi->getServices();

        return response()->json(['services' => $services]);
    }

    public function serviceAction(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:start,stop,restart',
        ]);

        $result = $this->engineApi->controlService($name, $validated['action']);

        return response()->json($result);
    }

    public function reboot(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        $result = $this->engineApi->rebootSystem();

        return response()->json([
            'message' => $result['message'] ?? 'reboot requested',
        ], 202);
    }

    public function processes(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }
        return response()->json([
            'processes' => $this->engineApi->getProcesses(),
        ]);
    }

    public function killProcess(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }
        $validated = $request->validate([
            'pid' => 'required|integer|min:2',
        ]);
        $result = $this->engineApi->killProcess((int) $validated['pid']);

        return response()->json($result);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'domains_count' => $user->domains()->count(),
            'databases_count' => $user->databases()->count(),
            'email_accounts_count' => $user->emailAccounts()->count(),
            'active_subscriptions' => $user->subscriptions()->where('status', 'active')->count(),
        ];

        if ($user->isAdmin()) {
            $data['total_users'] = User::count();
            $data['total_domains'] = Domain::count();
        }
        if ($user->isAdmin() || $user->isVendorOperator()) {
            $data['system_stats'] = $this->engineApi->getSystemStats();
        }

        return response()->json(['dashboard' => $data]);
    }

    public function panelHealth(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin() && ! $request->user()?->isVendorOperator()) {
            abort(403);
        }

        return response()->json($this->buildPanelHealthReport());
    }

    public function panelRepair(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin() && ! $request->user()?->isVendorOperator()) {
            abort(403);
        }

        $steps = [];

        $fixExit = Artisan::call('hostvim:fix-permissions');
        $fixOut = trim((string) Artisan::output());
        $steps[] = [
            'id' => 'artisan:hostvim-fix-permissions',
            'ok' => $fixExit === 0,
            'message' => $fixExit === 0
                ? ($fixOut !== '' ? substr($fixOut, 0, 400) : 'Dizinler ve chmod denendi')
                : 'hostvim:fix-permissions başarısız (kod '.$fixExit.')',
        ];

        $storageDirs = [
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];
        foreach ($storageDirs as $dir) {
            if (! File::isDirectory($dir)) {
                try {
                    File::ensureDirectoryExists($dir, 0755, true);
                    $steps[] = ['id' => 'mkdir:'.$dir, 'ok' => true, 'message' => 'Directory created'];
                } catch (\Throwable $e) {
                    $steps[] = ['id' => 'mkdir:'.$dir, 'ok' => false, 'message' => 'Directory create failed: '.$e->getMessage()];
                    continue;
                }
            }
            if (File::isWritable($dir)) {
                $steps[] = ['id' => 'writable:'.$dir, 'ok' => true, 'message' => 'Directory writable'];
                continue;
            }
            @chmod($dir, 0755);
            $steps[] = [
                'id' => 'chmod:'.$dir,
                'ok' => File::isWritable($dir),
                'message' => File::isWritable($dir) ? 'Permissions fixed (0755)' : 'Directory is not writable',
            ];
        }

        $publicRoot = public_path();
        $adminDir = $publicRoot.DIRECTORY_SEPARATOR.'admin';
        try {
            File::ensureDirectoryExists($adminDir, 0755, true);
            $steps[] = ['id' => 'mkdir:'.$adminDir, 'ok' => true, 'message' => 'Directory exists'];
        } catch (\Throwable $e) {
            $steps[] = ['id' => 'mkdir:'.$adminDir, 'ok' => false, 'message' => 'Directory create failed: '.$e->getMessage()];
        }

        $assetTarget = $publicRoot.DIRECTORY_SEPARATOR.'assets';
        $adminAssetLink = $adminDir.DIRECTORY_SEPARATOR.'assets';
        $this->repairSymlink($adminAssetLink, $assetTarget, $steps, 'admin_assets_symlink');

        $indexTarget = $publicRoot.DIRECTORY_SEPARATOR.'index.html';
        $adminIndexLink = $adminDir.DIRECTORY_SEPARATOR.'index.html';
        $this->repairSymlink($adminIndexLink, $indexTarget, $steps, 'admin_index_symlink');

        $steps[] = $this->runArtisanSafe('optimize:clear', 'artisan_optimize_clear');

        $report = $this->buildPanelHealthReport();
        $report['repair_steps'] = $steps;
        $failedChecks = array_values(array_filter(
            $report['checks'] ?? [],
            static fn (array $c): bool => ! (bool) ($c['ok'] ?? false)
        ));
        $failedSummary = implode('; ', array_map(
            static fn (array $c): string => sprintf('%s: %s', (string) ($c['id'] ?? 'unknown'), (string) ($c['message'] ?? 'failed')),
            array_slice($failedChecks, 0, 5)
        ));
        $report['message'] = $report['ok']
            ? 'Panel checks passed. Safe repairs applied.'
            : ('Panel checked. Some items still require manual action.'
                .($failedSummary !== '' ? ' Remaining: '.$failedSummary : ''));

        return response()->json($report);
    }

    /**
     * @return array{
     *   ok: bool,
     *   summary: array{total:int, passed:int, failed:int},
     *   checks: array<int, array{id:string, ok:bool, message:string}>
     * }
     */
    private function buildPanelHealthReport(): array
    {
        $checks = [];

        $storageDirs = [
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];
        foreach ($storageDirs as $dir) {
            $exists = File::isDirectory($dir);
            $writable = $exists && File::isWritable($dir);
            $checks[] = [
                'id' => 'dir:'.$dir,
                'ok' => $exists && $writable,
                'message' => $exists
                    ? ($writable ? 'Directory writable' : 'Directory is not writable')
                    : 'Directory missing',
            ];
        }

        $publicRoot = public_path();
        $adminDir = $publicRoot.DIRECTORY_SEPARATOR.'admin';
        $assetTarget = $publicRoot.DIRECTORY_SEPARATOR.'assets';
        $adminAssetLink = $adminDir.DIRECTORY_SEPARATOR.'assets';
        $checks[] = $this->symlinkCheck('admin_assets_symlink', $adminAssetLink, $assetTarget);

        $indexTarget = $publicRoot.DIRECTORY_SEPARATOR.'index.html';
        $adminIndexLink = $adminDir.DIRECTORY_SEPARATOR.'index.html';
        $checks[] = $this->symlinkCheck('admin_index_symlink', $adminIndexLink, $indexTarget);

        $engine = $this->engineApi->getSystemStats();
        $checks[] = [
            'id' => 'engine_connection',
            'ok' => ! empty($engine),
            'message' => ! empty($engine) ? 'Engine reachable' : 'Engine stats unavailable',
        ];

        $passed = count(array_filter($checks, fn (array $c): bool => (bool) ($c['ok'] ?? false)));
        $total = count($checks);

        return [
            'ok' => $passed === $total,
            'summary' => [
                'total' => $total,
                'passed' => $passed,
                'failed' => $total - $passed,
            ],
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<int, array{id:string, ok:bool, message:string}>  $steps
     */
    private function repairSymlink(string $linkPath, string $targetPath, array &$steps, string $id): void
    {
        if (is_link($linkPath)) {
            $current = @readlink($linkPath);
            if ($current !== false) {
                $resolved = $this->isAbsolutePath($current)
                    ? $current
                    : dirname($linkPath).DIRECTORY_SEPARATOR.$current;
                if (realpath($resolved) === realpath($targetPath)) {
                    $steps[] = ['id' => $id, 'ok' => true, 'message' => 'Symlink already valid'];

                    return;
                }
            }
            @unlink($linkPath);
        } elseif (file_exists($linkPath)) {
            $backup = $linkPath.'.bak.'.date('YmdHis');
            try {
                @rename($linkPath, $backup);
                if (file_exists($linkPath)) {
                    $steps[] = ['id' => $id, 'ok' => false, 'message' => 'Path exists and cannot be moved for symlink repair'];

                    return;
                }
                $steps[] = ['id' => $id.'_backup', 'ok' => true, 'message' => 'Existing path moved to '.$backup];
            } catch (\Throwable) {
                $steps[] = ['id' => $id, 'ok' => false, 'message' => 'Path exists and backup move failed'];

                return;
            }
        }

        $relative = $this->relativePath(dirname($linkPath), $targetPath);
        try {
            @symlink($relative, $linkPath);
            $ok = is_link($linkPath);
            $steps[] = ['id' => $id, 'ok' => $ok, 'message' => $ok ? 'Symlink repaired' : 'Symlink could not be created'];
        } catch (\Throwable) {
            $steps[] = ['id' => $id, 'ok' => false, 'message' => 'Symlink repair failed'];
        }
    }

    /**
     * @return array{id:string, ok:bool, message:string}
     */
    private function symlinkCheck(string $id, string $linkPath, string $targetPath): array
    {
        if (! is_link($linkPath)) {
            if (file_exists($linkPath)) {
                return ['id' => $id, 'ok' => false, 'message' => 'Path exists but is not a symlink'];
            }

            return ['id' => $id, 'ok' => false, 'message' => 'Symlink missing'];
        }

        $current = @readlink($linkPath);
        if ($current === false) {
            return ['id' => $id, 'ok' => false, 'message' => 'Symlink target unreadable'];
        }

        $resolved = $this->isAbsolutePath($current)
            ? $current
            : dirname($linkPath).DIRECTORY_SEPARATOR.$current;
        if (realpath($resolved) !== realpath($targetPath)) {
            return ['id' => $id, 'ok' => false, 'message' => 'Symlink points to unexpected target'];
        }

        return ['id' => $id, 'ok' => true, 'message' => 'Symlink valid'];
    }

    /**
     * @return array{id:string, ok:bool, message:string}
     */
    private function runArtisanSafe(string $command, string $id): array
    {
        try {
            Artisan::call($command);

            return ['id' => $id, 'ok' => true, 'message' => trim(Artisan::output()) !== '' ? trim(Artisan::output()) : 'ok'];
        } catch (\Throwable $e) {
            return ['id' => $id, 'ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function relativePath(string $fromDir, string $toPath): string
    {
        $from = explode(DIRECTORY_SEPARATOR, trim(realpath($fromDir) ?: $fromDir, DIRECTORY_SEPARATOR));
        $to = explode(DIRECTORY_SEPARATOR, trim(realpath($toPath) ?: $toPath, DIRECTORY_SEPARATOR));

        while (count($from) > 0 && count($to) > 0 && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('..'.DIRECTORY_SEPARATOR, count($from)).implode(DIRECTORY_SEPARATOR, $to);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
