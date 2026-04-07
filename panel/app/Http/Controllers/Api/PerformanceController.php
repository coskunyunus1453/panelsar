<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function show(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $res = $this->engine->getSitePerformance($domain->name);
        if (! empty($res['error'])) {
            return response()->json(['message' => (string) $res['error']], 503);
        }

        return response()->json([
            'domain' => $domain->name,
            'performance_mode' => $res['performance_mode'] ?? 'off',
            'server_type' => $res['server_type'] ?? null,
            'supported_servers' => $res['supported_servers'] ?? ['nginx'],
        ]);
    }

    public function update(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:off,standard'],
        ]);

        $res = $this->engine->setSitePerformance($domain->name, (string) $validated['mode']);
        if (! empty($res['error'])) {
            return response()->json(['message' => (string) $res['error']], 422);
        }

        return response()->json([
            'domain' => $domain->name,
            'performance_mode' => $res['performance_mode'] ?? $validated['mode'],
        ]);
    }
}

