<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\DomainService;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function __construct(
        private DomainService $domainService,
        private HostingQuotaService $quota,
        private EngineApiService $engine,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $domains = $request->user()->domains()
            ->with(['sslCertificate', 'databases'])
            ->latest()
            ->paginate(20);

        return response()->json($domains);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'php_version' => 'nullable|string|in:7.4,8.0,8.1,8.2,8.3,8.4',
            'server_type' => 'nullable|string|in:nginx,apache',
        ]);

        $this->quota->ensureCanCreateDomain($request->user());

        $domain = $this->domainService->create(
            $request->user(),
            $validated['name'],
            $validated['php_version'] ?? '8.2',
            $validated['server_type'] ?? 'nginx',
        );

        return response()->json([
            'message' => __('domains.created'),
            'domain' => $domain,
        ], 201);
    }

    public function show(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        return response()->json([
            'domain' => $domain->load([
                'sslCertificate', 'databases', 'emailAccounts',
                'dnsRecords', 'backups',
            ]),
        ]);
    }

    public function logs(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);
        $lines = (int) $request->integer('lines', 200);
        $lines = max(20, min(1000, $lines));

        $result = $this->engine->getSiteLogs($domain->name, $lines);
        if (! empty($result['error'])) {
            return response()->json([
                'message' => (string) $result['error'],
            ], 502);
        }

        return response()->json($result);
    }

    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('delete', $domain);

        $got = trim((string) $request->input('confirmation', ''));
        $candidates = array_values(array_unique(array_filter(array_map('trim', [
            (string) __('domains.delete_confirm_expected'),
            'SILMEKİSTİYORUM',
            'DELETEALLDATA',
        ]))));
        $ok = false;
        foreach ($candidates as $c) {
            if ($c !== '' && hash_equals($c, $got)) {
                $ok = true;
                break;
            }
        }
        if (! $ok) {
            return response()->json([
                'message' => __('domains.delete_confirm_mismatch'),
            ], 422);
        }

        $this->domainService->delete($domain);

        return response()->json(['message' => __('domains.deleted')]);
    }

    public function setStatus(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validated = $request->validate([
            'status' => 'required|string|in:active,suspended',
        ]);

        try {
            $this->domainService->setPanelStatus($domain, $validated['status']);
        } catch (\Throwable $e) {
            report($e);
            $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 503;
            if (! is_int($code) || $code < 400 || $code > 599) {
                $code = 503;
            }
            $msg = $e->getMessage() ?: __('domains.status_updated');
            if (EngineApiService::isLikelyConnectionFailure($msg)) {
                $msg = 'Engine servisine ulasilamiyor. ENGINE_API_URL, ENGINE_INTERNAL_KEY ve hostvim-engine servisini kontrol edin.';
            }

            return response()->json([
                'message' => $msg,
            ], $code);
        }

        return response()->json([
            'message' => __('domains.status_updated'),
            'domain' => $domain->fresh(),
        ]);
    }

    public function switchServer(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validated = $request->validate([
            'server_type' => 'required|string|in:nginx,apache',
        ]);

        try {
            $this->domainService->switchServerType($domain, $validated['server_type']);
        } catch (\Throwable $e) {
            report($e);
            $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 503;
            if (! is_int($code) || $code < 400 || $code > 599) {
                $code = 503;
            }
            $msg = $e->getMessage() ?: __('domains.server_switched');
            if (EngineApiService::isLikelyConnectionFailure($msg)) {
                $msg = 'Engine servisine ulasilamiyor. ENGINE_API_URL, ENGINE_INTERNAL_KEY ve hostvim-engine servisini kontrol edin.';
            }

            return response()->json([
                'message' => $msg,
            ], $code);
        }

        return response()->json([
            'message' => __('domains.server_switched'),
            'domain' => $domain->fresh(),
        ]);
    }

    public function switchPhp(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validated = $request->validate([
            'php_version' => 'required|string|in:7.4,8.0,8.1,8.2,8.3,8.4',
        ]);

        try {
            $this->domainService->switchPhpVersion($domain, $validated['php_version']);
        } catch (\Throwable $e) {
            report($e);
            $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 503;
            if (! is_int($code) || $code < 400 || $code > 599) {
                $code = 503;
            }
            $msg = $e->getMessage() ?: __('domains.php_switched');
            if (EngineApiService::isLikelyConnectionFailure($msg)) {
                $msg = 'Engine servisine ulasilamiyor. ENGINE_API_URL, ENGINE_INTERNAL_KEY ve hostvim-engine servisini kontrol edin.';
            }

            return response()->json([
                'message' => $msg,
            ], $code);
        }

        return response()->json([
            'message' => __('domains.php_switched'),
            'domain' => $domain->fresh(),
        ]);
    }
}
