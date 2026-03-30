<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
    ) {}

    public function userSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'domains' => $user->domains()->count(),
            'databases' => $user->databases()->count(),
            'email_accounts' => $user->emailAccounts()->count(),
            'disk_estimate_mb' => $user->databases()->sum('size_mb'),
        ]);
    }

    public function server(Request $request): JsonResponse
    {
        return response()->json([
            'stats' => $this->engine->getSystemStats(),
            'services' => $this->engine->getServices(),
        ]);
    }
}
