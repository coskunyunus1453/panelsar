<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\FtpAccount;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FtpController extends Controller
{
    use AuthorizesUserDomain;

    public function __construct(
        private EngineApiService $engine,
        private HostingQuotaService $quota,
    ) {}

    public function index(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $local = $request->user()->ftpAccounts()->where('domain_id', $domain->id)->get();

        return response()->json([
            'local' => $local,
            'engine' => $this->engine->ftpList($domain->name),
        ]);
    }

    public function store(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'username' => 'required|string|max:32',
            'home_directory' => 'required|string|max:255',
            'quota_mb' => 'nullable|integer|min:-1',
        ]);

        $this->quota->ensureCanCreateFtpAccount($request->user());

        $password = Str::random(16);

        $account = FtpAccount::create([
            'user_id' => $request->user()->id,
            'domain_id' => $domain->id,
            'username' => $validated['username'],
            'password' => $password,
            'home_directory' => $validated['home_directory'],
            'quota_mb' => $validated['quota_mb'] ?? -1,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => __('ftp.created'),
            'account' => $account,
            'password_plain' => $password,
            'engine' => $this->engine->ftpProvision($domain->name, array_merge($validated, ['password' => $password])),
        ], 201);
    }

    public function destroy(Request $request, FtpAccount $ftpAccount): JsonResponse
    {
        if ($ftpAccount->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $ftpAccount->loadMissing('domain');
        $domainName = $ftpAccount->domain?->name;
        if ($domainName !== null) {
            $this->engine->ftpDeleteAccount($domainName, $ftpAccount->username);
        }
        $ftpAccount->delete();

        return response()->json(['message' => __('ftp.deleted')]);
    }
}
