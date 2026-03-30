<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Models\Domain;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            $engine = $this->engine->installerRun('wordpress', $domain->name, $payload);

            if (! empty($engine['error'])) {
                if (EngineApiService::isLikelyConnectionFailure($engine['error'])) {
                    return response()->json([
                        'message' => __('installer.engine_unreachable', [
                            'url' => config('panelsar.engine_url'),
                        ]),
                        'hint' => __('installer.engine_start_hint'),
                        'engine' => $engine,
                    ], 503);
                }

                return response()->json([
                    'message' => $engine['error'],
                    'engine' => $engine,
                ], 502);
            }

            return response()->json([
                'message' => __('installer.completed'),
                'engine' => $engine,
            ], 200);
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
}
