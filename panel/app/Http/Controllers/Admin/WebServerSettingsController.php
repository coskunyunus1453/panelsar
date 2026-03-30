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
}

