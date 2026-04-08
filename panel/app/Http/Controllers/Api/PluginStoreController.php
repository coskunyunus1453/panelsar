<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunPluginMigrationJob;
use App\Models\Domain;
use App\Models\PluginMigrationRun;
use App\Models\PluginModule;
use App\Models\UserPluginModule;
use App\Services\SafeAuditLogger;
use App\Support\MigrationCliResolver;
use App\Support\MigrationSsh;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        RunPluginMigrationJob::dispatch($run->id)->afterResponse();

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
            'skip_db' => ['sometimes', 'boolean'],
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
        $skipDb = (bool) ($v['skip_db'] ?? false);
        if (! $skipDb) {
            $checks[] = $this->checkMysqlClientResolved();
        } else {
            $checks[] = [
                'key' => 'bin_mysql',
                'ok' => true,
                'message' => 'mysql client check skipped (files-only preflight)',
            ];
        }

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
            'source_domain' => ['nullable', 'string', 'max:255'],
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
            $domain = trim((string) ($v['source_domain'] ?? ''));
            $paths = match ($sourceType) {
                'cpanel' => [
                    '/home/'.$v['source_user'].'/public_html',
                    '/home/'.$v['source_user'].'/www',
                ],
                'plesk' => $this->pleskDiscoverPathCandidates($domain, (string) $v['source_host']),
                'aapanel' => $this->aapanelDiscoverPathCandidates($domain, (string) $v['source_host']),
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
            $suggestedDbUser = (string) $v['source_user'];

            return response()->json([
                'source_type' => $sourceType,
                'suggested_source_path' => $bestPath,
                'path_candidates' => $pathChecks,
                'db_names' => $dbNames,
                'suggested_db_user' => $suggestedDbUser,
                'db_users' => [$suggestedDbUser],
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

    /**
     * @return array{key:string, ok:bool, message:string}
     */
    private function checkMysqlClientResolved(): array
    {
        $path = MigrationCliResolver::mysql();
        if ($path !== null) {
            return [
                'key' => 'bin_mysql',
                'ok' => true,
                'message' => 'mysql client: '.$path,
            ];
        }

        return [
            'key' => 'bin_mysql',
            'ok' => false,
            'message' => 'mysql client missing (install mysql client or set MYSQL_CLIENT_PATH in .env; XAMPP: /Applications/XAMPP/xamppfiles/bin/mysql)',
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
            $p = new Process(
                array_merge(
                    MigrationSsh::commandPrefix($tmpKey, (int) $port),
                    [$user.'@'.$host, $remoteCmd]
                ),
                null,
                MigrationSsh::processEnv(),
                null,
                25
            );
            $p->run();

            $ok = $p->isSuccessful() && str_contains($p->getOutput(), 'OK');
            $detail = '';
            if (! $ok) {
                $detail = trim($p->getErrorOutput() ?: '');
                if ($detail === '') {
                    $detail = trim($p->getOutput() ?: '');
                }
                if ($detail === 'OK') {
                    $detail = '';
                }
                if ($detail !== '' && strlen($detail) > 280) {
                    $detail = substr($detail, 0, 280).'…';
                }
            }

            $hint = ' Hint: On the source server this SSH user must be able to access the path (often use root, '
                .'or the user that owns the site files). aaPanel: folder name must match Website > Domain; '
                .'otherwise set Custom web root in the wizard.';

            return [
                'key' => 'remote_path',
                'ok' => $ok,
                'message' => $ok
                    ? 'remote path reachable'
                    : 'remote path check failed'.($detail !== '' ? ' — '.$detail : '').$hint,
            ];
        } finally {
            @unlink($tmpKey);
        }
    }

    private function remoteTestDir(string $keyPath, int $port, string $userAtHost, string $path): bool
    {
        $remoteCmd = sprintf('test -d %s && echo OK', escapeshellarg($path));
        $p = new Process(
            array_merge(
                MigrationSsh::commandPrefix($keyPath, (int) $port),
                [$userAtHost, $remoteCmd]
            ),
            null,
            MigrationSsh::processEnv(),
            null,
            25
        );
        $p->run();

        return $p->isSuccessful() && str_contains($p->getOutput(), 'OK');
    }

    /**
     * @return list<string>
     */
    private function pleskDiscoverPathCandidates(string $domain, string $sourceHost): array
    {
        $candidates = [];
        if ($domain !== '') {
            $candidates[] = '/var/www/vhosts/'.$domain.'/httpdocs';
        }
        if ($sourceHost !== '' && filter_var($sourceHost, FILTER_VALIDATE_IP) === false) {
            $candidates[] = '/var/www/vhosts/'.$sourceHost.'/httpdocs';
        }
        if ($candidates === []) {
            $candidates[] = '/var/www/vhosts/'.$sourceHost.'/httpdocs';
        }

        return array_values(array_unique($candidates));
    }

    /**
     * aaPanel: site genelde /www/wwwroot/{alan_adi}
     *
     * @return list<string>
     */
    private function aapanelDiscoverPathCandidates(string $domain, string $sourceHost): array
    {
        $candidates = [];
        if ($domain !== '') {
            $candidates[] = '/www/wwwroot/'.$domain;
            $candidates[] = '/www/wwwroot/'.$domain.'/public';
        }
        if ($sourceHost !== '' && filter_var($sourceHost, FILTER_VALIDATE_IP) === false) {
            $candidates[] = '/www/wwwroot/'.$sourceHost;
            $candidates[] = '/www/wwwroot/'.$sourceHost.'/public';
        }
        $candidates[] = '/www/wwwroot';

        return array_values(array_unique($candidates));
    }

    private function remoteDiscoverDatabases(string $keyPath, int $port, string $userAtHost): array
    {
        $filter = "sed '/^information_schema\$/d;/^performance_schema\$/d;/^mysql\$/d;/^sys\$/d' | head -n 50";
        $cmds = [
            "mysql -N -e 'SHOW DATABASES' 2>/dev/null | ".$filter,
            "mariadb -N -e 'SHOW DATABASES' 2>/dev/null | ".$filter,
            "ls -1 /var/lib/mysql 2>/dev/null | sed '/^mysql\$/d;/^performance_schema\$/d;/^information_schema\$/d;/^sys\$/d' | head -n 50",
        ];
        foreach ($cmds as $cmd) {
            $p = new Process(
                array_merge(
                    MigrationSsh::commandPrefix($keyPath, (int) $port),
                    [$userAtHost, $cmd]
                ),
                null,
                MigrationSsh::processEnv(),
                null,
                25
            );
            $p->run();
            if (! $p->isSuccessful()) {
                continue;
            }
            $lines = array_values(array_filter(array_map('trim', explode("\n", trim($p->getOutput())))));
            if ($lines !== []) {
                return $lines;
            }
        }

        return [];
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
