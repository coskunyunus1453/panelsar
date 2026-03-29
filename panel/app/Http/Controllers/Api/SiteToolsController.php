<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteToolsController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function run(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'tool' => ['required', 'string', Rule::in(['composer', 'npm'])],
            'action' => ['required', 'string', Rule::in(['install', 'update', 'dump-autoload', 'ci'])],
        ]);

        if ($validated['tool'] === 'composer' && $validated['action'] === 'ci') {
            return response()->json(['message' => __('tools.invalid_combo')], 422);
        }

        if ($validated['tool'] === 'npm' && in_array($validated['action'], ['update', 'dump-autoload'], true)) {
            return response()->json(['message' => __('tools.invalid_combo')], 422);
        }

        $engine = $this->engine->runSiteTool($domain->name, $validated['tool'], $validated['action']);

        if (! empty($engine['error'])) {
            return response()->json([
                'message' => $engine['error'],
                'output' => $engine['output'] ?? '',
                'engine' => $engine,
            ], 503);
        }

        return response()->json([
            'message' => __('tools.completed'),
            'output' => $engine['output'] ?? '',
            'engine' => $engine,
        ]);
    }
}
