<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StackController extends Controller
{
    public function modules(EngineApiService $engine): JsonResponse
    {
        return response()->json(['modules' => $engine->getStackModules()]);
    }

    public function install(Request $request, EngineApiService $engine): JsonResponse
    {
        $validated = $request->validate([
            'bundle_id' => 'required|string|max:120',
        ]);

        $result = $engine->installStackBundle($validated['bundle_id']);

        if (! empty($result['error'])) {
            if (EngineApiService::isLikelyConnectionFailure($result['error'])) {
                return response()->json([
                    'message' => __('installer.engine_unreachable', [
                        'url' => config('panelsar.engine_url'),
                    ]),
                    'hint' => __('installer.engine_start_hint'),
                    'engine' => $result,
                ], 503);
            }

            return response()->json([
                'message' => $result['error'],
                'output' => $result['output'] ?? null,
                'engine' => $result,
            ], 502);
        }

        return response()->json([
            'message' => __('stack.install_ok'),
            'modules' => $result['modules'] ?? [],
            'output' => $result['output'] ?? null,
        ]);
    }
}
