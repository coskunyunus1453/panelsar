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
        if (! empty($result['error'])) {
            return $this->securityErrorResponse($result['error'], $result);
        }

        return response()->json(['result' => $result], 200);
    }

    public function installFail2ban(): JsonResponse
    {
        $result = $this->engine->installFail2ban();
        if (! empty($result['error'])) {
            return $this->securityErrorResponse($result['error'], $result);
        }

        return response()->json(['result' => $result], 200);
    }

    public function toggleModSecurity(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);
        $result = $this->engine->toggleModSecurity((bool) $validated['enabled']);
        if (! empty($result['error'])) {
            return $this->securityErrorResponse($result['error'], $result);
        }

        return response()->json(['result' => $result], 200);
    }

    public function installModSecurity(): JsonResponse
    {
        $result = $this->engine->installModSecurity();
        if (! empty($result['error'])) {
            return $this->securityErrorResponse($result['error'], $result);
        }

        return response()->json(['result' => $result], 200);
    }

    public function toggleClamav(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);
        $result = $this->engine->toggleClamav((bool) $validated['enabled']);
        if (! empty($result['error'])) {
            return $this->securityErrorResponse($result['error'], $result);
        }

        return response()->json(['result' => $result], 200);
    }

    public function scanClamav(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'nullable|string|max:255',
        ]);
        $result = $this->engine->runClamavScan($validated['target'] ?? null);
        if (! empty($result['error'])) {
            return $this->securityErrorResponse($result['error'], $result);
        }

        return response()->json(['result' => $result], 200);
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
        if (! empty($result['error'])) {
            return $this->securityErrorResponse($result['error'], $result);
        }

        return response()->json(['result' => $result], 200);
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
        if (! empty($result['error'])) {
            return $this->securityErrorResponse($result['error'], $result);
        }

        return response()->json(['result' => $result], 200);
    }

    private function securityErrorResponse(mixed $rawError, array $result): JsonResponse
    {
        $err = is_string($rawError) ? trim($rawError) : 'security operation failed';
        $hint = null;
        $code = 502;
        $lower = strtolower($err);

        if (str_contains($lower, 'sudo') || str_contains($lower, 'panelsar-security')) {
            $code = 422;
            $hint = 'Sunucuda /usr/local/sbin/panelsar-security ve sudoers NOPASSWD kuralını kontrol edin. Gerekirse deploy/bootstrap/install-production.sh yeniden çalıştırın.';
        } elseif (str_contains($lower, 'missing /etc/modsecurity') || str_contains($lower, 'modsecurity')) {
            $code = 422;
            $hint = 'ModSecurity yapılandırması bulunamadı. Sunucuda Apache + modsecurity2 kurulu mu kontrol edin.';
        } elseif (str_contains($lower, 'systemctl') || str_contains($lower, 'unit') || str_contains($lower, 'not found')) {
            $code = 422;
            $hint = 'İlgili servis paketi (fail2ban/modsecurity/clamav) yüklü veya etkin olmayabilir.';
        }

        return response()->json([
            'message' => $err,
            'hint' => $hint,
            'result' => $result,
        ], $code);
    }
}
