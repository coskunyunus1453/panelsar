<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunPluginMigrationJob;
use App\Models\Domain;
use App\Models\PluginMigrationRun;
use App\Models\PluginModule;
use App\Models\UserPluginModule;
use App\Services\SafeAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Process\Process;

class PluginStoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureCatalog();
        $user = $request->user();
        $mods = PluginModule::query()
            ->where('is_public', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
        $userMap = UserPluginModule::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('plugin_module_id');

        $rows = $mods->map(function (PluginModule $m) use ($userMap) {
            $u = $userMap->get($m->id);

            return [
                'id' => $m->id,
                'slug' => $m->slug,
                'name' => $m->name,
                'summary' => $m->summary,
                'category' => $m->category,
                'version' => $m->version,
                'is_paid' => (bool) $m->is_paid,
                'price_cents' => (int) $m->price_cents,
                'currency' => $m->currency,
                'config' => $m->config ?? [],
                'installed' => $u !== null,
                'active' => (bool) ($u?->is_active ?? false),
                'status' => $u?->status ?? null,
            ];
        })->values();

        return response()->json(['modules' => $rows]);
    }

    public function install(Request $request, PluginModule $pluginModule): JsonResponse
    {
        $user = $request->user();
        if (! $pluginModule->is_public) {
            return response()->json(['message' => 'module not available'], 404);
        }
        $row = UserPluginModule::firstOrCreate(
            ['user_id' => $user->id, 'plugin_module_id' => $pluginModule->id],
            ['status' => 'installed', 'is_active' => false, 'installed_at' => now()]
        );
        if (! $row->installed_at) {
            $row->installed_at = now();
            $row->status = 'installed';
            $row->save();
        }

        return response()->json(['message' => 'module installed']);
    }

    public function activate(Request $request, PluginModule $pluginModule): JsonResponse
    {
        $user = $request->user();
        $row = UserPluginModule::query()
            ->where('user_id', $user->id)
            ->where('plugin_module_id', $pluginModule->id)
            ->first();
        if (! $row) {
            return response()->json(['message' => 'module not installed'], 422);
        }
        $row->is_active = true;
        $row->status = 'active';
        $row->activated_at = now();
        $row->save();
        SafeAuditLogger::info('hostvim.plugin_audit', [
            'action' => 'activate',
            'user_id' => $user->id,
            'plugin' => $pluginModule->slug,
        ], $request);

        return response()->json(['message' => 'module activated']);
    }

    public function deactivate(Request $request, PluginModule $pluginModule): JsonResponse
    {
        $user = $request->user();
        $row = UserPluginModule::query()
            ->where('user_id', $user->id)
            ->where('plugin_module_id', $pluginModule->id)
            ->first();
        if (! $row) {
            return response()->json(['message' => 'module not installed'], 422);
        }
        $row->is_active = false;
        $row->status = 'installed';
        $row->save();
        SafeAuditLogger::info('hostvim.plugin_audit', [
            'action' => 'deactivate',
            'user_id' => $user->id,
            'plugin' => $pluginModule->slug,
        ], $request);

        return response()->json(['message' => 'module deactivated']);
    }

    public function runs(Request $request): JsonResponse
    {
        $user = $request->user();
        $runs = PluginMigrationRun::query()
            ->with('pluginModule:id,slug,name')
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(function (PluginMigrationRun $run) {
                return [
                    'id' => $run->id,
                    'plugin' => $run->pluginModule ? [
                        'id' => $run->pluginModule->id,
                        'slug' => $run->pluginModule->slug,
                        'name' => $run->pluginModule->name,
                    ] : null,
                    'source_type' => $run->source_type,
                    'target_domain_id' => $run->target_domain_id,
                    'source_host' => $run->source_host,
                    'status' => $run->status,
                    'dry_run' => (bool) $run->dry_run,
                    'progress' => (int) $run->progress,
                    'output' => $run->output,
                    'error_message' => $run->error_message,
                    'created_at' => optional($run->created_at)->toIso8601String(),
                    'started_at' => optional($run->started_at)->toIso8601String(),
                    'finished_at' => optional($run->finished_at)->toIso8601String(),
                ];
            })->values();

        return response()->json(['runs' => $runs]);
    }

    public function startMigration(Request $request, PluginModule $pluginModule): JsonResponse
    {
        $user = $request->user();
        $installed = UserPluginModule::query()
            ->where('user_id', $user->id)
            ->where('plugin_module_id', $pluginModule->id)
            ->where('is_active', true)
            ->exists();
        if (! $installed) {
            return response()->json(['message' => 'module must be active'], 422);
        }
        if ($pluginModule->category !== 'migration') {
            return response()->json(['message' => 'not a migration module'], 422);
        }

        $sourceType = (string) data_get($pluginModule->config ?? [], 'source', '');
        $request->validate([
            'source_host' => ['required', 'string', 'max:255'],
            'source_port' => ['nullable', 'integer', 'between:1,65535'],
            'source_user' => ['required', 'string', 'max:120'],
            'target_domain_id' => ['required', 'integer', 'exists:domains,id'],
            'source_path' => ['required', 'string', 'max:255'],
            'auth_type' => ['required', 'in:password,token,ssh_key'],
            'password' => ['nullable', 'string', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:255'],
            'ssh_private_key' => ['nullable', 'string', 'max:8192'],
            'source_db_name' => ['nullable', 'string', 'max:120'],
            'source_db_user' => ['nullable', 'string', 'max:120'],
            'source_db_password' => ['nullable', 'string', 'max:255'],
            'source_db_host' => ['nullable', 'string', 'max:255'],
            'source_db_port' => ['nullable', 'integer', 'between:1,65535'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        if (! in_array($sourceType, ['plesk', 'cpanel', 'aapanel'], true)) {
            return response()->json(['message' => 'unsupported source type'], 422);
        }
        if ($request->string('auth_type')->toString() === 'password' && ! $request->filled('password')) {
            return response()->json(['message' => 'password required'], 422);
        }
        if ($request->string('auth_type')->toString() === 'token' && ! $request->filled('api_token')) {
            return response()->json(['message' => 'api token required'], 422);
        }
        if ($request->string('auth_type')->toString() === 'ssh_key' && ! $request->filled('ssh_private_key')) {
            return response()->json(['message' => 'ssh private key required'], 422);
        }
        $targetDomain = Domain::query()
            ->where('id', (int) $request->input('target_domain_id'))
            ->where('user_id', $user->id)
            ->first();
        if (! $targetDomain) {
            return response()->json(['message' => 'invalid target domain'], 422);
        }

        $enc = fn (?string $v): ?string => filled($v) ? Crypt::encryptString((string) $v) : null;

        $run = PluginMigrationRun::query()->create([
            'user_id' => $user->id,
            'plugin_module_id' => $pluginModule->id,
            'target_domain_id' => $targetDomain->id,
            'source_type' => $sourceType,
            'source_host' => $request->string('source_host')->toString(),
            'source_port' => (int) ($request->input('source_port') ?: 22),
            'source_user' => $request->input('source_user'),
            'status' => 'queued',
            'dry_run' => (bool) $request->boolean('dry_run', true),
            'progress' => 0,
            'options' => [
                'auth_type' => $request->string('auth_type')->toString(),
                'source_path' => $request->input('source_path'),
                'secret_password' => $enc($request->input('password')),
                'secret_api_token' => $enc($request->input('api_token')),
                'secret_ssh_private_key' => $enc($request->input('ssh_private_key')),
                'source_db_name' => $request->input('source_db_name'),
                'source_db_user' => $request->input('source_db_user'),
                'source_db_host' => $request->input('source_db_host', '127.0.0.1'),
                'source_db_port' => (int) ($request->input('source_db_port') ?: 3306),
                'secret_source_db_password' => $enc($request->input('source_db_password')),
                'has_secret' => $request->filled('password') || $request->filled('api_token'),
            ],
            'output' => null,
            'error_message' => null,
        ]);
        Bus::dispatch(new RunPluginMigrationJob($run->id))->afterResponse();

        SafeAuditLogger::info('hostvim.plugin_audit', [
            'action' => 'migration_start',
            'user_id' => $user->id,
            'plugin' => $pluginModule->slug,
            'run_id' => $run->id,
            'source_type' => $sourceType,
            'source_host_fp' => SafeAuditLogger::hostFingerprint($run->source_host),
        ], $request);

        return response()->json([
            'message' => 'migration queued',
            'run_id' => $run->id,
        ]);
    }

    public function preflight(Request $request, PluginModule $pluginModule): JsonResponse
    {
        $user = $request->user();
        $installed = UserPluginModule::query()
            ->where('user_id', $user->id)
            ->where('plugin_module_id', $pluginModule->id)
            ->where('is_active', true)
            ->exists();
        if (! $installed) {
            return response()->json(['message' => 'module must be active'], 422);
        }

        $v = $request->validate([
            'source_host' => ['required', 'string', 'max:255'],
            'source_port' => ['nullable', 'integer', 'between:1,65535'],
            'source_user' => ['required', 'string', 'max:120'],
            'target_domain_id' => ['required', 'integer', 'exists:domains,id'],
            'source_path' => ['required', 'string', 'max:255'],
            'auth_type' => ['required', 'in:password,token,ssh_key'],
            'ssh_private_key' => ['nullable', 'string', 'max:8192'],
            'source_db_name' => ['nullable', 'string', 'max:120'],
            'source_db_user' => ['nullable', 'string', 'max:120'],
            'source_db_password' => ['nullable', 'string', 'max:255'],
            'source_db_host' => ['nullable', 'string', 'max:255'],
            'source_db_port' => ['nullable', 'integer', 'between:1,65535'],
        ]);

        $targetDomain = Domain::query()
            ->where('id', (int) $v['target_domain_id'])
            ->where('user_id', $user->id)
            ->first();
        if (! $targetDomain) {
            return response()->json(['message' => 'invalid target domain'], 422);
        }

        $checks = [];
        $checks[] = $this->checkLocalBinary('rsync');
        $checks[] = $this->checkLocalBinary('ssh');
        $checks[] = $this->checkLocalBinary('mysql');

        if (($v['auth_type'] ?? '') === 'ssh_key') {
            if (! filled($v['ssh_private_key'] ?? null)) {
                $checks[] = ['key' => 'ssh_key', 'ok' => false, 'message' => 'ssh private key required'];
            } else {
                $checks[] = ['key' => 'ssh_key', 'ok' => true, 'message' => 'ssh private key provided'];
                $checks[] = $this->checkRemotePath(
                    (string) $v['source_host'],
                    (int) ($v['source_port'] ?? 22),
                    (string) $v['source_user'],
                    (string) $v['source_path'],
                    (string) $v['ssh_private_key']
                );
            }
        }

        $ok = collect($checks)->every(fn ($c) => (bool) ($c['ok'] ?? false));

        return response()->json([
            'ok' => $ok,
            'checks' => $checks,
            'target_domain' => [
                'id' => $targetDomain->id,
                'name' => $targetDomain->name,
                'document_root' => $targetDomain->document_root,
            ],
        ]);
    }

    public function discover(Request $request, PluginModule $pluginModule): JsonResponse
    {
        $user = $request->user();
        $installed = UserPluginModule::query()
            ->where('user_id', $user->id)
            ->where('plugin_module_id', $pluginModule->id)
            ->where('is_active', true)
            ->exists();
        if (! $installed) {
            return response()->json(['message' => 'module must be active'], 422);
        }

        $v = $request->validate([
            'source_host' => ['required', 'string', 'max:255'],
            'source_port' => ['nullable', 'integer', 'between:1,65535'],
            'source_user' => ['required', 'string', 'max:120'],
            'auth_type' => ['required', 'in:ssh_key'],
            'ssh_private_key' => ['required', 'string', 'max:8192'],
        ]);

        $sourceType = (string) data_get($pluginModule->config ?? [], 'source', '');
        if (! in_array($sourceType, ['cpanel', 'plesk', 'aapanel'], true)) {
            return response()->json(['message' => 'unsupported source type'], 422);
        }

        $tmpKey = storage_path('app/tmp/plugin-discover-'.md5($v['source_user'].$v['source_host'].microtime(true)).'.key');
        @mkdir(dirname($tmpKey), 0750, true);
        file_put_contents($tmpKey, (string) $v['ssh_private_key']);
        @chmod($tmpKey, 0600);

        try {
            $port = (int) ($v['source_port'] ?? 22);
            $userAtHost = $v['source_user'].'@'.$v['source_host'];
            $paths = match ($sourceType) {
                'cpanel' => [
                    '/home/'.$v['source_user'].'/public_html',
                    '/home/'.$v['source_user'].'/www',
                ],
                'plesk' => [
                    '/var/www/vhosts/'.$v['source_host'].'/httpdocs',
                    '/var/www/vhosts/'.$v['source_host'].'/subdomains',
                ],
                default => [
                    '/www/wwwroot/'.$v['source_host'],
                    '/www/wwwroot',
                ],
            };

            $pathChecks = [];
            foreach ($paths as $path) {
                $ok = $this->remoteTestDir($tmpKey, $port, $userAtHost, $path);
                $pathChecks[] = ['path' => $path, 'ok' => $ok];
            }
            $bestPath = collect($pathChecks)->firstWhere('ok', true)['path'] ?? null;

            $dbNames = $this->remoteDiscoverDatabases($tmpKey, $port, $userAtHost);
            $dbUsers = $this->remoteDiscoverDbUsers($tmpKey, $port, $userAtHost);

            return response()->json([
                'source_type' => $sourceType,
                'suggested_source_path' => $bestPath,
                'path_candidates' => $pathChecks,
                'db_names' => $dbNames,
                'db_users' => $dbUsers,
            ]);
        } finally {
            @unlink($tmpKey);
        }
    }

    private function checkLocalBinary(string $bin): array
    {
        $p = new Process(['sh', '-lc', 'command -v '.escapeshellarg($bin)]);
        $p->run();

        return [
            'key' => 'bin_'.$bin,
            'ok' => $p->isSuccessful(),
            'message' => $p->isSuccessful() ? $bin.' available' : $bin.' missing',
        ];
    }

    private function checkRemotePath(string $host, int $port, string $user, string $path, string $privateKey): array
    {
        $tmpKey = storage_path('app/tmp/plugin-preflight-'.md5($user.$host.$path.microtime(true)).'.key');
        @mkdir(dirname($tmpKey), 0750, true);
        file_put_contents($tmpKey, $privateKey);
        @chmod($tmpKey, 0600);
        try {
            $remoteCmd = sprintf('test -d %s && echo OK', escapeshellarg($path));
            $p = new Process([
                'ssh',
                '-i', $tmpKey,
                '-o', 'StrictHostKeyChecking=accept-new',
                '-p', (string) $port,
                $user.'@'.$host,
                $remoteCmd,
            ], null, null, null, 20);
            $p->run();

            return [
                'key' => 'remote_path',
                'ok' => $p->isSuccessful() && str_contains($p->getOutput(), 'OK'),
                'message' => $p->isSuccessful() ? 'remote path reachable' : 'remote path check failed',
            ];
        } finally {
            @unlink($tmpKey);
        }
    }

    private function remoteTestDir(string $keyPath, int $port, string $userAtHost, string $path): bool
    {
        $remoteCmd = sprintf('test -d %s && echo OK', escapeshellarg($path));
        $p = new Process([
            'ssh',
            '-i', $keyPath,
            '-o', 'StrictHostKeyChecking=accept-new',
            '-p', (string) $port,
            $userAtHost,
            $remoteCmd,
        ], null, null, null, 20);
        $p->run();

        return $p->isSuccessful() && str_contains($p->getOutput(), 'OK');
    }

    private function remoteDiscoverDatabases(string $keyPath, int $port, string $userAtHost): array
    {
        $cmd = "ls -1 /var/lib/mysql 2>/dev/null | sed '/^mysql$/d;/^performance_schema$/d;/^information_schema$/d;/^sys$/d' | head -n 30";
        $p = new Process([
            'ssh',
            '-i', $keyPath,
            '-o', 'StrictHostKeyChecking=accept-new',
            '-p', (string) $port,
            $userAtHost,
            $cmd,
        ], null, null, null, 20);
        $p->run();
        if (! $p->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", trim($p->getOutput())))));
    }

    private function remoteDiscoverDbUsers(string $keyPath, int $port, string $userAtHost): array
    {
        $cmd = "awk -F: '{print $1}' /etc/passwd | head -n 30";
        $p = new Process([
            'ssh',
            '-i', $keyPath,
            '-o', 'StrictHostKeyChecking=accept-new',
            '-p', (string) $port,
            $userAtHost,
            $cmd,
        ], null, null, null, 20);
        $p->run();
        if (! $p->isSuccessful()) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", trim($p->getOutput())))));
    }

    private function ensureCatalog(): void
    {
        $defaults = [
            [
                'slug' => 'migration-plesk',
                'name' => 'Plesk Migration',
                'summary' => 'Plesk kaynak sunucudan site, veritabani ve e-posta tasima asistani.',
                'category' => 'migration',
                'version' => '1.0.0',
                'config' => ['source' => 'plesk', 'wizard_steps' => 4],
            ],
            [
                'slug' => 'migration-cpanel',
                'name' => 'cPanel Migration',
                'summary' => 'cPanel hesabindan domain, dosya ve mysql tasima modulu.',
                'category' => 'migration',
                'version' => '1.0.0',
                'config' => ['source' => 'cpanel', 'wizard_steps' => 5],
            ],
            [
                'slug' => 'migration-aapanel',
                'name' => 'aaPanel Migration',
                'summary' => 'aaPanel altyapisindan paneller arasi tasima yardimcisi.',
                'category' => 'migration',
                'version' => '1.0.0',
                'config' => ['source' => 'aapanel', 'wizard_steps' => 4],
            ],
        ];
        foreach ($defaults as $d) {
            PluginModule::query()->updateOrCreate(
                ['slug' => $d['slug']],
                array_merge([
                    'is_public' => true,
                    'is_paid' => false,
                    'price_cents' => 0,
                    'currency' => 'USD',
                ], $d)
            );
        }
    }
}
