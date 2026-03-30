<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhpSettingsController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function versions(): JsonResponse
    {
        return response()->json([
            'versions' => $this->engine->getPhpVersions(),
        ]);
    }

    public function ini(string $version): JsonResponse
    {
        $data = $this->engine->getPhpIni($version);

        return response()->json($data);
    }

    public function updateIni(Request $request, string $version): JsonResponse
    {
        $validated = $request->validate([
            'ini' => 'required|string|max:500000',
            'reload' => 'sometimes|boolean',
        ]);

        $payload = [
            'ini' => (string) $validated['ini'],
        ];
        if (array_key_exists('reload', $validated)) {
            $payload['reload'] = (bool) $validated['reload'];
        }

        $res = $this->engine->updatePhpIni($version, $payload);

        return response()->json($res);
    }

    public function modules(string $version): JsonResponse
    {
        $data = $this->engine->getPhpModules($version);

        return response()->json($data);
    }

    public function updateModules(Request $request, string $version): JsonResponse
    {
        $validated = $request->validate([
            'modules' => 'required|array|min:1',
            'modules.*.directive' => 'required|string|in:extension,zend_extension',
            'modules.*.name' => 'required|string|max:120',
            'modules.*.enabled' => 'required|boolean',
            'reload' => 'sometimes|boolean',
        ]);

        $payload = [
            'modules' => array_map(static fn ($m) => [
                'directive' => $m['directive'],
                'name' => $m['name'],
                'enabled' => (bool) $m['enabled'],
            ], $validated['modules']),
        ];

        if (array_key_exists('reload', $validated)) {
            $payload['reload'] = (bool) $validated['reload'];
        }

        $res = $this->engine->updatePhpModules($version, $payload);

        return response()->json($res);
    }
}

