<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesUserDomain;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailAccountController extends Controller
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

        return response()->json([
            'mail' => $this->engine->mailOverview($domain->name),
            'accounts' => $request->user()->emailAccounts()->where('domain_id', $domain->id)->get(),
        ]);
    }

    public function store(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->userOwnsDomain($request, $domain)) {
            abort(403);
        }
        $validated = $request->validate([
            'local_part' => 'required|string|max:64',
            'quota_mb' => 'nullable|integer|min:1',
        ]);

        $this->quota->ensureCanCreateEmailAccount($request->user());

        $email = $validated['local_part'].'@'.$domain->name;
        $password = Str::random(16);

        $account = EmailAccount::create([
            'user_id' => $request->user()->id,
            'domain_id' => $domain->id,
            'email' => $email,
            'password' => $password,
            'quota_mb' => $validated['quota_mb'] ?? 500,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => __('email.created'),
            'account' => $account,
            'password_plain' => $password,
            'engine' => $this->engine->mailCreateMailbox($domain->name, [
                'email' => $email,
                'password' => $password,
                'quota_mb' => $account->quota_mb,
            ]),
        ], 201);
    }

    public function update(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        if ($emailAccount->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $validated = $request->validate([
            'forwarding_address' => 'nullable|email',
            'autoresponder_enabled' => 'boolean',
            'autoresponder_message' => 'nullable|string',
            'quota_mb' => 'nullable|integer|min:1',
        ]);
        $emailAccount->update($validated);

        return response()->json(['message' => __('email.updated'), 'account' => $emailAccount->fresh()]);
    }

    public function destroy(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        if ($emailAccount->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403);
        }
        $emailAccount->loadMissing('domain');
        $domainName = $emailAccount->domain?->name;
        if ($domainName !== null) {
            $this->engine->mailDeleteMailbox($domainName, $emailAccount->email);
        }
        $emailAccount->delete();

        return response()->json(['message' => __('email.deleted')]);
    }
}
