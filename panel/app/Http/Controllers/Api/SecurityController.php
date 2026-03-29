<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $overview = $this->engine->securityOverview();

        return response()->json(['overview' => $overview]);
    }

    public function firewall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:allow,deny',
            'protocol' => 'required|string|in:tcp,udp,icmp,any',
            'port' => 'nullable|string',
            'source' => 'nullable|string|max:64',
        ]);

        return response()->json([
            'result' => $this->engine->applyFirewallRule($validated),
        ]);
    }
}
