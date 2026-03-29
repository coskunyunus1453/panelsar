<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Database;
use App\Models\Domain;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $payload = [
            'db_host' => $db->host,
            'db_port' => $db->port,
            'db_name' => $db->name,
            'db_user' => $db->username,
            'db_password' => $db->password,
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
    }
}
