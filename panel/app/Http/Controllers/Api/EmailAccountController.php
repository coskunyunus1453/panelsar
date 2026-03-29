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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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
            'autoresponder_enabled' => 'sometimes|boolean',
            'autoresponder_message' => 'nullable|string',
            'quota_mb' => 'nullable|integer|min:1',
            'password' => 'nullable|string|min:8|max:128',
            'regenerate_password' => 'sometimes|boolean',
        ]);

        $emailAccount->loadMissing('domain');
        $domainName = $emailAccount->domain?->name;

        $plainPassword = null;
        if ($request->boolean('regenerate_password')) {
            $plainPassword = Str::random(16);
        } elseif (! empty($validated['password'])) {
            $plainPassword = $validated['password'];
        }

        $fill = Arr::except($validated, ['password', 'regenerate_password']);
        $emailAccount->fill($fill);

        if ($plainPassword !== null) {
            $emailAccount->password = $plainPassword;
        }

        $emailAccount->save();

        $enginePatch = ['email' => $emailAccount->email];
        if ($plainPassword !== null) {
            $enginePatch['password'] = $plainPassword;
        }
        if (array_key_exists('quota_mb', $validated) && $validated['quota_mb'] !== null) {
            $enginePatch['quota_mb'] = (int) $validated['quota_mb'];
        }

        if ($domainName !== null && (count($enginePatch) > 1)) {
            $res = $this->engine->mailPatchMailbox($domainName, $enginePatch);
            if (isset($res['error']) && is_string($res['error']) && $res['error'] !== '') {
                Log::warning('Engine mailPatchMailbox failed', [
                    'domain' => $domainName,
                    'email' => $emailAccount->email,
                    'error' => $res['error'],
                ]);
            }
        }

        $payload = [
            'message' => $plainPassword !== null
                ? __('email.password_changed')
                : __('email.updated'),
            'account' => $emailAccount->fresh(),
        ];
        if ($plainPassword !== null) {
            $payload['password_plain'] = $plainPassword;
        }

        return response()->json($payload);
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
