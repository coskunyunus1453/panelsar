<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\User;
use App\Services\EngineApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function __construct(
        private EngineApiService $engineApi,
    ) {}

    public function stats(): JsonResponse
    {
        $stats = $this->engineApi->getSystemStats();

        return response()->json(['stats' => $stats]);
    }

    public function services(): JsonResponse
    {
        $services = $this->engineApi->getServices();

        return response()->json(['services' => $services]);
    }

    public function serviceAction(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:start,stop,restart',
        ]);

        $result = $this->engineApi->controlService($name, $validated['action']);

        return response()->json($result);
    }

    public function reboot(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        $result = $this->engineApi->rebootSystem();

        return response()->json([
            'message' => $result['message'] ?? 'reboot requested',
        ], 202);
    }

    public function processes(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }
        return response()->json([
            'processes' => $this->engineApi->getProcesses(),
        ]);
    }

    public function killProcess(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }
        $validated = $request->validate([
            'pid' => 'required|integer|min:2',
        ]);
        $result = $this->engineApi->killProcess((int) $validated['pid']);

        return response()->json($result);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'domains_count' => $user->domains()->count(),
            'databases_count' => $user->databases()->count(),
            'email_accounts_count' => $user->emailAccounts()->count(),
            'active_subscriptions' => $user->subscriptions()->where('status', 'active')->count(),
        ];

        if ($user->isAdmin()) {
            $data['total_users'] = User::count();
            $data['total_domains'] = Domain::count();
        }
        if ($user->isAdmin() || $user->isVendorOperator()) {
            $data['system_stats'] = $this->engineApi->getSystemStats();
        }

        return response()->json(['dashboard' => $data]);
    }
}
