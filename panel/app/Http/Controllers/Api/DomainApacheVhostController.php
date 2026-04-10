<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DomainApacheVhostController extends Controller
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

        $res = $this->engine->getSiteApacheVhost($domain->name);
        if (! empty($res['error'])) {
            $low = Str::lower((string) $res['error']);
            $code = Str::contains($low, ['not found', 'site meta not found']) ? 404 : 422;

            return response()->json([
                'message' => (string) $res['error'],
                'path' => $res['path'] ?? null,
                'hint' => $res['hint'] ?? null,
                'can_revert' => (bool) ($res['can_revert'] ?? false),
                'content' => '',
            ], $code);
        }

        return response()->json([
            'domain' => $domain->name,
            'path' => $res['path'] ?? null,
            'content' => $res['content'] ?? '',
            'can_revert' => (bool) ($res['can_revert'] ?? false),
        ]);
    }

    public function update(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:524288'],
        ]);

        $res = $this->engine->updateSiteApacheVhost($domain->name, (string) $validated['content']);
        if (! empty($res['error'])) {
            return response()->json([
                'message' => (string) $res['error'],
                'path' => $res['path'] ?? null,
            ], 422);
        }

        return response()->json([
            'domain' => $domain->name,
            'path' => $res['path'] ?? null,
            'message' => $res['message'] ?? 'ok',
            'ok' => (bool) ($res['ok'] ?? true),
            'can_revert' => (bool) ($res['can_revert'] ?? false),
        ]);
    }

    public function revert(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }

        $res = $this->engine->revertSiteApacheVhost($domain->name);
        if (! empty($res['error'])) {
            return response()->json([
                'message' => (string) $res['error'],
                'path' => $res['path'] ?? null,
            ], 422);
        }

        return response()->json([
            'domain' => $domain->name,
            'path' => $res['path'] ?? null,
            'message' => $res['message'] ?? 'ok',
            'ok' => (bool) ($res['ok'] ?? true),
            'can_revert' => (bool) ($res['can_revert'] ?? false),
        ]);
    }
}
