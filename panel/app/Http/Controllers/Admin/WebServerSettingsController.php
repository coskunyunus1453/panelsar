<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebServerSettingsController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'settings' => $this->engine->getWebServerSettings(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nginx_manage_vhosts' => 'sometimes|boolean',
            'nginx_reload_after_vhost' => 'sometimes|boolean',
            'apache_manage_vhosts' => 'sometimes|boolean',
            'apache_reload_after_vhost' => 'sometimes|boolean',

            'openlitespeed_manage_vhosts' => 'sometimes|boolean',
            'openlitespeed_conf_root' => 'sometimes|nullable|string|max:255',
            'openlitespeed_reload_after_vhost' => 'sometimes|boolean',
            'openlitespeed_ctrl_path' => 'sometimes|nullable|string|max:255',

            'php_fpm_manage_pools' => 'sometimes|boolean',
            'php_fpm_reload_after_pool' => 'sometimes|boolean',
            'php_fpm_socket' => 'sometimes|nullable|string|max:255',
            'php_fpm_listen_dir' => 'sometimes|nullable|string|max:255',
            'php_fpm_pool_dir_template' => 'sometimes|nullable|string|max:255',
            'php_fpm_pool_user' => 'sometimes|nullable|string|max:64',
            'php_fpm_pool_group' => 'sometimes|nullable|string|max:64',

            'reload' => 'sometimes|boolean',
        ]);

        $result = $this->engine->updateWebServerSettings($validated);

        return response()->json([
            'message' => $result['message'] ?? 'ok',
            'settings' => $result['settings'] ?? $this->engine->getWebServerSettings(),
            'reload' => $result['reload'] ?? null,
        ]);
    }

    public function apacheModules(): JsonResponse
    {
        $result = $this->engine->getApacheModules();

        return response()->json([
            'modules' => $result['modules'] ?? [],
        ]);
    }

    public function services(): JsonResponse
    {
        return response()->json([
            'services' => $this->engine->getWebServerServices(),
        ]);
    }

    public function setApacheModule(Request $request, string $module): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $result = $this->engine->setApacheModule($module, (bool) $validated['enabled']);

        return response()->json([
            'module' => $result['module'] ?? $module,
            'enabled' => (bool) ($result['enabled'] ?? false),
        ]);
    }

    public function getNginxConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => 'sometimes|string|in:main,panel',
        ]);
        $scope = (string) ($validated['scope'] ?? 'main');
        $result = $this->engine->getNginxConfig($scope);

        return response()->json([
            'scope' => $result['scope'] ?? $scope,
            'content' => (string) ($result['content'] ?? ''),
        ]);
    }

    public function updateNginxConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => 'required|string|in:main,panel',
            'content' => 'required|string|max:300000',
            'test_reload' => 'sometimes|boolean',
        ]);
        $result = $this->engine->updateNginxConfig(
            (string) $validated['scope'],
            (string) $validated['content'],
            (bool) ($validated['test_reload'] ?? true)
        );

        return response()->json([
            'message' => $result['message'] ?? 'ok',
            'scope' => $result['scope'] ?? $validated['scope'],
        ]);
    }
}

