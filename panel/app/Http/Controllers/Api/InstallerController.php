<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Models\Domain;
use App\Models\InstallerRun;
use App\Jobs\RunInstallerJob;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallerController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function apps(): JsonResponse
    {
        return response()->json(['apps' => $this->engine->installerApps()]);
    }

    public function install(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        try {
            $validated = $request->validate([
                'app' => 'required|string|in:wordpress,joomla,laravel,drupal,prestashop',
                'database_id' => 'nullable|integer|exists:databases,id',
                'table_prefix' => 'nullable|string|regex:/^[a-zA-Z0-9_]{1,16}$/',
            ]);

            if ($validated['app'] !== 'wordpress') {
                return response()->json(['message' => __('installer.automated_only_wordpress')], 422);
            }

            if (empty($validated['database_id'])) {
                return response()->json(['message' => __('installer.wordpress_requires_db')], 422);
            }

            $db = Database::query()
                ->where('user_id', $request->user()->id)
                ->where('type', 'mysql')
                ->find($validated['database_id']);

            if (! $db) {
                return response()->json(['message' => __('installer.wordpress_mysql_db')], 422);
            }

            $prefix = trim((string) ($validated['table_prefix'] ?? ''));
            if ($prefix === '') {
                $prefix = 'wp_';
            }

            try {
                $dbPassword = $db->password;
            } catch (Throwable $e) {
                Log::warning('installer: veritabanı şifresi çözülemedi', [
                    'database_id' => $db->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['message' => __('installer.db_password_decrypt')], 422);
            }

            $dbHost = trim((string) ($db->host));
            if ($dbHost === '') {
                $dbHost = (string) config('panelsar.mysql_provision.host', config('database.connections.mysql.host', '127.0.0.1'));
            }

            $dbPort = (int) ($db->port ?? 3306);
            if ($dbPort < 1 || $dbPort > 65535) {
                $dbPort = 3306;
            }

            $payload = [
                'db_host' => $dbHost,
                'db_port' => $dbPort,
                'db_name' => $db->name,
                'db_user' => $db->username,
                'db_password' => $dbPassword,
                'table_prefix' => $prefix,
            ];
            $run = InstallerRun::query()->create([
                'user_id' => $request->user()->id,
                'domain_id' => $domain->id,
                'app' => 'wordpress',
                'status' => 'queued',
                'message' => __('installer.started'),
            ]);

            $isSyncQueue = (string) config('queue.default', 'sync') === 'sync';
            if ($isSyncQueue) {
                // Queue worker yoksa kullanıcıyı yanıltmamak için aynı requestte çalıştır.
                (new RunInstallerJob($run->id, $domain->name, 'wordpress', $payload))->handle($this->engine);

                $run->refresh();
                if ($run->status === 'failed') {
                    if (EngineApiService::isLikelyConnectionFailure($run->message)) {
                        return response()->json([
                            'message' => __('installer.engine_unreachable', [
                                'url' => config('panelsar.engine_url'),
                            ]),
                            'hint' => __('installer.engine_start_hint'),
                            'run_id' => $run->id,
                            'background' => false,
                        ], 503);
                    }

                    return response()->json([
                        'message' => $run->message ?: __('installer.unexpected_error'),
                        'run_id' => $run->id,
                        'background' => false,
                    ], 502);
                }

                return response()->json([
                    'message' => __('installer.completed_sync'),
                    'run_id' => $run->id,
                    'status' => $run->status,
                    'background' => false,
                ]);
            }

            Bus::dispatch(new RunInstallerJob($run->id, $domain->name, 'wordpress', $payload))->afterResponse();
            return response()->json([
                'message' => __('installer.started_background'),
                'run_id' => $run->id,
                'status' => 'queued',
                'background' => true,
            ], 202);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('installer: beklenmeyen hata', [
                'domain_id' => $domain->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = config('app.debug')
                ? $e->getMessage()
                : __('installer.unexpected_error');

            return response()->json(['message' => $message], 500);
        }
    }

    public function runs(Request $request): JsonResponse
    {
        $runs = InstallerRun::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'domain_id', 'app', 'status', 'message', 'started_at', 'finished_at', 'created_at']);

        return response()->json(['runs' => $runs]);
    }

    public function runShow(Request $request, InstallerRun $installerRun): JsonResponse
    {
        if ((int) $installerRun->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'run' => [
                'id' => $installerRun->id,
                'domain_id' => $installerRun->domain_id,
                'app' => $installerRun->app,
                'status' => $installerRun->status,
                'message' => $installerRun->message,
                'output' => $installerRun->output,
                'started_at' => optional($installerRun->started_at)->toIso8601String(),
                'finished_at' => optional($installerRun->finished_at)->toIso8601String(),
                'created_at' => optional($installerRun->created_at)->toIso8601String(),
            ],
        ]);
    }
}
