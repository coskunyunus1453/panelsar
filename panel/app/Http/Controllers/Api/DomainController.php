<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\DomainService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function __construct(
        private DomainService $domainService,
        private HostingQuotaService $quota,
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
            'name' => 'required|string|unique:domains,name|max:255',
            'php_version' => 'nullable|string|in:7.4,8.0,8.1,8.2,8.3',
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

    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('delete', $domain);

        $this->domainService->delete($domain);

        return response()->json(['message' => __('domains.deleted')]);
    }

    public function switchPhp(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('update', $domain);

        $validated = $request->validate([
            'php_version' => 'required|string|in:7.4,8.0,8.1,8.2,8.3',
        ]);

        $this->domainService->switchPhpVersion($domain, $validated['php_version']);

        return response()->json([
            'message' => __('domains.php_switched'),
            'domain' => $domain->fresh(),
        ]);
    }
}
