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

    public function toggleFail2ban(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);
        $result = $this->engine->toggleFail2ban((bool) $validated['enabled']);
        $code = empty($result['error']) ? 200 : 502;

        return response()->json(['result' => $result], $code);
    }

    public function toggleModSecurity(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);
        $result = $this->engine->toggleModSecurity((bool) $validated['enabled']);
        $code = empty($result['error']) ? 200 : 502;

        return response()->json(['result' => $result], $code);
    }

    public function toggleClamav(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);
        $result = $this->engine->toggleClamav((bool) $validated['enabled']);
        $code = empty($result['error']) ? 200 : 502;

        return response()->json(['result' => $result], $code);
    }

    public function scanClamav(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'nullable|string|max:255',
        ]);
        $result = $this->engine->runClamavScan($validated['target'] ?? null);
        $code = empty($result['error']) ? 200 : 502;

        return response()->json(['result' => $result], $code);
    }

    public function updateFail2banJail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bantime' => 'required|integer|min:60|max:604800',
            'findtime' => 'required|integer|min:60|max:604800',
            'maxretry' => 'required|integer|min:1|max:20',
        ]);

        $result = $this->engine->updateFail2banJail(
            (int) $validated['bantime'],
            (int) $validated['findtime'],
            (int) $validated['maxretry']
        );
        $code = empty($result['error']) ? 200 : 502;

        return response()->json(['result' => $result], $code);
    }

    public function reconcileMailState(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dry_run' => 'sometimes|boolean',
            'confirm' => 'nullable|string|max:128',
        ]);
        $dryRun = array_key_exists('dry_run', $validated) ? (bool) $validated['dry_run'] : true;
        $confirm = isset($validated['confirm']) ? (string) $validated['confirm'] : null;
        $result = $this->engine->reconcileMailState($dryRun, $confirm);
        $code = empty($result['error']) ? 200 : 502;

        return response()->json(['result' => $result], $code);
    }
}
