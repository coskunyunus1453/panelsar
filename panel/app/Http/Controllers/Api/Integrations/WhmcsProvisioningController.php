<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\HostingPackage;
use App\Models\Role;
use App\Models\User;
use App\Services\DomainService;
use App\Services\HostnameReservationService;
use App\Services\HostingQuotaService;
use App\Services\SafeAuditLogger;
use App\Services\SslIssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * WHMCS server module: panel kullanıcısı + (isteğe bağlı) engine’de site/alan adı, askı, fesih.
 *
 * @see https://developers.whmcs.com/provisioning-modules/
 */
class WhmcsProvisioningController extends Controller
{
    public function __construct(
        private DomainService $domainService,
        private HostingQuotaService $quota,
        private HostnameReservationService $hostnames,
        private SslIssueService $sslIssue,
    ) {}

    public function test(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'panel' => 'hostvim',
            'version' => config('hostvim.version', '0.1.0'),
        ]);
    }

    public function provision(Request $request): JsonResponse
    {
        $allowedLocales = $this->allowedLocales();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'hosting_package_id' => ['nullable', 'integer', 'exists:hosting_packages,id'],
            'locale' => ['nullable', 'string', Rule::in($allowedLocales)],
            'force_password_change' => ['sometimes', 'boolean'],
            'domain' => ['nullable', 'string', 'max:253'],
            'php_version' => ['nullable', 'string', 'in:7.4,8.0,8.1,8.2,8.3,8.4'],
            'server_type' => ['nullable', 'string', 'in:nginx,apache,openlitespeed'],
            'issue_lets_encrypt' => ['sometimes', 'boolean'],
            'lets_encrypt_email' => ['nullable', 'email'],
        ]);

        if (User::query()->where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'Bu e-posta ile zaten bir panel kullanıcısı var.',
            ], 409);
        }

        $role = Role::query()->where('name', 'user')->where('guard_name', 'web')->firstOrFail();

        $php = $validated['php_version'] ?? '8.2';
        $serverType = $validated['server_type'] ?? 'nginx';
        $domainName = isset($validated['domain']) ? strtolower(trim((string) $validated['domain'])) : '';
        $domainName = $domainName !== '' ? $domainName : null;

        $sslResult = null;
        $createdDomain = null;

        try {
            DB::transaction(function () use ($validated, $role, $php, $serverType, $domainName, &$sslResult, &$createdDomain, $request): void {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'locale' => $validated['locale'] ?? config('hostvim.default_locale', 'en'),
                    'status' => 'active',
                    'hosting_package_id' => $validated['hosting_package_id'] ?? null,
                    'hosting_package_manual_override' => array_key_exists('hosting_package_id', $validated),
                    'force_password_change' => (bool) ($validated['force_password_change'] ?? false),
                ]);
                $user->syncRoles([$role->name]);

                if ($domainName !== null) {
                    $this->quota->ensureCanCreateDomain($user);
                    $this->hostnames->assertPrimaryDomainForUser($user, $domainName);
                    $createdDomain = $this->domainService->create($user, $domainName, $php, $serverType);

                    if ($request->boolean('issue_lets_encrypt')) {
                        $sslResult = $this->sslIssue->issue(
                            $user,
                            $createdDomain->fresh(),
                            $validated['lets_encrypt_email'] ?? null,
                            config('hostvim.lets_encrypt_email') ?: null
                        );
                    }
                }
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Doğrulama hatası.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);
            if ($e instanceof HttpExceptionInterface) {
                $code = $e->getStatusCode();
                $msg = $e->getMessage() ?: 'Provision başarısız.';

                return response()->json(['message' => $msg], $code);
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Provision başarısız.',
            ], 503);
        }

        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        SafeAuditLogger::info('hostvim.whmcs.provision', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower(trim((string) $user->email))),
            'domain' => $domainName,
        ], $request);

        $payload = [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'status' => $user->status,
                'hosting_package_id' => $user->hosting_package_id,
            ],
        ];

        if ($createdDomain !== null) {
            $payload['domain'] = [
                'id' => $createdDomain->id,
                'name' => $createdDomain->name,
                'status' => $createdDomain->status,
                'php_version' => $createdDomain->php_version,
                'server_type' => $createdDomain->server_type,
            ];
        }
        if ($sslResult !== null) {
            $payload['ssl'] = [
                'ok' => (bool) ($sslResult['ok'] ?? false),
                'message' => (string) ($sslResult['message'] ?? ''),
            ];
        }

        return response()->json($payload, 201);
    }

    public function suspend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = $this->findUserOrFail($validated['email']);
        $user->update(['status' => 'suspended']);
        $user->tokens()->delete();

        $siteErrors = [];
        foreach ($user->domains()->get() as $domain) {
            if (in_array($domain->status, ['deleting'], true)) {
                continue;
            }
            try {
                $this->domainService->setPanelStatus($domain, 'suspended');
            } catch (Throwable $e) {
                report($e);
                $siteErrors[] = $domain->name.': '.$e->getMessage();
            }
        }

        SafeAuditLogger::info('hostvim.whmcs.suspend', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower(trim((string) $user->email))),
            'site_errors' => $siteErrors !== [] ? $siteErrors : null,
        ], $request);

        return response()->json([
            'message' => 'suspended',
            'user_id' => $user->id,
            'site_errors' => $siteErrors !== [] ? $siteErrors : null,
        ]);
    }

    public function unsuspend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = $this->findUserOrFail($validated['email']);
        $user->update(['status' => 'active']);

        $siteErrors = [];
        foreach ($user->domains()->get() as $domain) {
            if ($domain->status !== 'suspended') {
                continue;
            }
            try {
                $this->domainService->setPanelStatus($domain, 'active');
            } catch (Throwable $e) {
                report($e);
                $siteErrors[] = $domain->name.': '.$e->getMessage();
            }
        }

        SafeAuditLogger::info('hostvim.whmcs.unsuspend', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower(trim((string) $user->email))),
            'site_errors' => $siteErrors !== [] ? $siteErrors : null,
        ], $request);

        return response()->json([
            'message' => 'active',
            'user_id' => $user->id,
            'site_errors' => $siteErrors !== [] ? $siteErrors : null,
        ]);
    }

    public function terminate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'delete_sites' => ['sometimes', 'boolean'],
        ]);

        $user = $this->findUserOrFail($validated['email']);
        $deleteSites = (bool) ($validated['delete_sites'] ?? true);

        if ($deleteSites) {
            foreach ($user->domains()->get() as $domain) {
                $this->domainService->delete($domain);
            }
        }

        $user->update(['status' => 'disabled']);
        $user->tokens()->delete();

        SafeAuditLogger::warning('hostvim.whmcs.terminate', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower(trim((string) $user->email))),
            'delete_sites' => $deleteSites,
        ], $request);

        return response()->json([
            'message' => $deleteSites ? 'terminated' : 'disabled',
            'user_id' => $user->id,
            'delete_sites' => $deleteSites,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user = $this->findUserOrFail($validated['email']);
        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();
        $user->tokens()->delete();

        SafeAuditLogger::info('hostvim.whmcs.change_password', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower(trim((string) $user->email))),
        ], $request);

        return response()->json(['message' => 'password_updated', 'user_id' => $user->id]);
    }

    public function changePackage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'hosting_package_id' => ['nullable', 'integer', 'exists:hosting_packages,id'],
        ]);

        $user = $this->findUserOrFail($validated['email']);
        $user->update([
            'hosting_package_id' => $validated['hosting_package_id'] ?? null,
            'hosting_package_manual_override' => true,
        ]);

        SafeAuditLogger::info('hostvim.whmcs.change_package', [
            'user_id' => $user->id,
            'hosting_package_id' => $user->hosting_package_id,
        ], $request);

        return response()->json([
            'message' => 'package_updated',
            'user_id' => $user->id,
            'hosting_package_id' => $user->hosting_package_id,
        ]);
    }

    /**
     * Tek site için PHP / web sunucusu (WHMCS özel komut veya dış otomasyon).
     */
    public function updateSite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'php_version' => ['nullable', 'string', 'in:7.4,8.0,8.1,8.2,8.3,8.4'],
            'server_type' => ['nullable', 'string', 'in:nginx,apache,openlitespeed'],
        ]);

        if (($validated['php_version'] ?? null) === null && ($validated['server_type'] ?? null) === null) {
            return response()->json(['message' => 'php_version veya server_type belirtin.'], 422);
        }

        $user = $this->findUserOrFail($validated['email']);
        $host = strtolower(trim($validated['domain']));
        $domain = $user->domains()->where('name', $host)->first();
        if ($domain === null) {
            return response()->json(['message' => 'Alan adı bu müşteriye ait değil.'], 404);
        }

        try {
            if (($validated['php_version'] ?? null) !== null) {
                $this->domainService->switchPhpVersion($domain, $validated['php_version']);
                $domain->refresh();
            }
            if (($validated['server_type'] ?? null) !== null) {
                $this->domainService->switchServerType($domain, $validated['server_type']);
            }
        } catch (Throwable $e) {
            report($e);
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Site güncellenemedi.',
                ], $e->getStatusCode());
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Site güncellenemedi.',
            ], 503);
        }

        $domain->refresh();

        SafeAuditLogger::info('hostvim.whmcs.site_update', [
            'user_id' => $user->id,
            'domain' => $domain->name,
        ], $request);

        return response()->json([
            'message' => 'site_updated',
            'domain' => [
                'id' => $domain->id,
                'name' => $domain->name,
                'php_version' => $domain->php_version,
                'server_type' => $domain->server_type,
            ],
        ]);
    }

    /** WHMCS ChangeDomain: motor + panel birincil alan adı. */
    public function changeDomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'old_domain' => ['required', 'string', 'max:253'],
            'new_domain' => ['required', 'string', 'max:253'],
        ]);

        $user = $this->findUserOrFail($validated['email']);
        $old = strtolower(trim($validated['old_domain']));
        $new = strtolower(trim($validated['new_domain']));

        $domain = $user->domains()->where('name', $old)->first();
        if ($domain === null) {
            return response()->json(['message' => 'Eski alan adı bu müşteriye ait değil.'], 404);
        }

        try {
            $domain = $this->domainService->renamePrimarySite($user, $domain, $new);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Doğrulama hatası.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Alan adı değiştirilemedi.',
                ], $e->getStatusCode());
            }

            return response()->json(['message' => $e->getMessage() ?: 'Alan adı değiştirilemedi.'], 503);
        }

        SafeAuditLogger::info('hostvim.whmcs.change_domain', [
            'user_id' => $user->id,
            'old_domain' => $old,
            'new_domain' => $new,
        ], $request);

        return response()->json([
            'message' => 'domain_renamed',
            'domain' => [
                'id' => $domain->id,
                'name' => $domain->name,
                'document_root' => $domain->document_root,
            ],
        ]);
    }

    /**
     * WHMCS Renew: faturalama yenilemesinde panel tarafında kalıcı iş yok; denetim günlüğü ve doğrulama.
     */
    public function serviceRenew(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['nullable', 'string', 'max:253'],
        ]);

        $user = $this->findUserOrFail($validated['email']);
        $dom = isset($validated['domain']) ? strtolower(trim((string) $validated['domain'])) : '';
        if ($dom !== '') {
            $has = Domain::query()->where('user_id', $user->id)->where('name', $dom)->exists();
            if (! $has) {
                return response()->json(['message' => 'Alan adı bu müşteriye ait değil.'], 404);
            }
        }

        SafeAuditLogger::info('hostvim.whmcs.service_renew', [
            'user_id' => $user->id,
            'domain' => $dom !== '' ? $dom : null,
        ], $request);

        return response()->json([
            'message' => 'renew_acknowledged',
            'user_id' => $user->id,
        ]);
    }

    /** Paket listesi: WHMCS ürün yapılandırmasında ID eşlemesi için. */
    public function packages(): JsonResponse
    {
        $packages = HostingPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'price_monthly', 'price_yearly']);

        return response()->json(['packages' => $packages]);
    }

    private function findUserOrFail(string $email): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            abort(response()->json(['message' => 'Kullanıcı bulunamadı.'], 404));
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    private function allowedLocales(): array
    {
        $raw = config('hostvim.available_locales', ['en']);
        if (is_string($raw)) {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }
        if (is_array($raw)) {
            return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $raw)));
        }

        return ['en'];
    }
}
