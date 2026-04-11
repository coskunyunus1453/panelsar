<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupDestination;
use App\Models\CronJob;
use App\Models\Database;
use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\EmailAccount;
use App\Models\EmailForwarder;
use App\Models\FtpAccount;
use App\Models\User;
use App\Services\BindZoneTextParser;
use App\Services\DatabaseService;
use App\Services\EngineApiService;
use App\Services\HostingQuotaService;
use App\Services\SafeAuditLogger;
use App\Services\SslIssueService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * WHMCS / dış otomasyon: e-posta, FTP, veritabanı, cron, kullanım özeti.
 */
class WhmcsResourcesController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
        private HostingQuotaService $quota,
        private DatabaseService $databaseService,
        private SslIssueService $sslIssue,
    ) {}

    /** Tüm aktif siteler — WHMCS UsageUpdate (sunucu başına günlük). */
    public function usageAccounts(): JsonResponse
    {
        $domains = Domain::query()
            ->where('status', '!=', 'deleting')
            ->with(['user.hostingPackage'])
            ->orderBy('id')
            ->get();

        $accounts = [];
        foreach ($domains as $domain) {
            $user = $domain->user;
            if ($user === null) {
                continue;
            }
            $pkg = $user->hostingPackage;
            $disk = $this->engine->getSiteDiskUsage($domain->name);
            $bytes = (int) ($disk['bytes'] ?? 0);
            $diskusage = (int) max(0, round($bytes / 1048576));
            $disklimit = ($pkg !== null && (int) $pkg->disk_space_mb > 0)
                ? (int) $pkg->disk_space_mb
                : 0;
            $trafficBytes = $this->engine->getSiteTrafficSampleBytesTotal($domain->name, 8000);
            $bwusage = (int) max(0, round($trafficBytes / 1048576));
            $bwlimit = ($pkg !== null && (int) $pkg->bandwidth_mb > 0)
                ? (int) $pkg->bandwidth_mb
                : 0;

            $accounts[] = [
                'domain' => $domain->name,
                'diskusage' => $diskusage,
                'disklimit' => $disklimit,
                'bandwidth' => $bwusage,
                'bwlimit' => $bwlimit,
            ];
        }

        return response()->json(['accounts' => $accounts]);
    }

    /** Tek site disk (MB) + paket limitleri. */
    public function usageDomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);
        $pkg = $user->hostingPackage;
        $disk = $this->engine->getSiteDiskUsage($domain->name);
        $bytes = (int) ($disk['bytes'] ?? 0);
        $trafficBytes = $this->engine->getSiteTrafficSampleBytesTotal($domain->name, 8000);

        return response()->json([
            'domain' => $domain->name,
            'diskusage_mb' => (int) max(0, round($bytes / 1048576)),
            'disklimit_mb' => ($pkg !== null && (int) $pkg->disk_space_mb > 0) ? (int) $pkg->disk_space_mb : null,
            'bandwidth_usage_mb' => (int) max(0, round($trafficBytes / 1048576)),
            'bandwidth_limit_mb' => ($pkg !== null && (int) $pkg->bandwidth_mb > 0) ? (int) $pkg->bandwidth_mb : null,
            'engine_error' => $disk['error'] ?? null,
        ]);
    }

    public function emailCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'local_part' => ['required', 'string', 'max:64'],
            'quota_mb' => ['nullable', 'integer', 'min:1'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);
        $this->quota->ensureCanCreateEmailAccount($user);

        $mailbox = strtolower($validated['local_part']).'@'.$domain->name;
        $password = $validated['password'] ?? null;
        if ($password === null || $password === '') {
            $password = Str::random(16);
        }

        $account = EmailAccount::create([
            'user_id' => $user->id,
            'domain_id' => $domain->id,
            'email' => $mailbox,
            'password' => $password,
            'quota_mb' => $validated['quota_mb'] ?? 500,
            'status' => 'active',
        ]);

        $engine = $this->engine->mailCreateMailbox($domain->name, [
            'email' => $mailbox,
            'password' => $password,
            'quota_mb' => $account->quota_mb,
        ]);

        SafeAuditLogger::info('hostvim.whmcs.email_create', [
            'user_id' => $user->id,
            'domain' => $domain->name,
        ], $request);

        return response()->json([
            'account' => $account,
            'password_plain' => $password,
            'engine' => $engine,
        ], 201);
    }

    public function emailDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'mailbox' => ['required', 'string', 'max:255'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $mailbox = strtolower(trim($validated['mailbox']));
        $account = EmailAccount::query()
            ->where('user_id', $user->id)
            ->where('email', $mailbox)
            ->first();
        if ($account === null) {
            return response()->json(['message' => 'Posta kutusu bulunamadı.'], 404);
        }
        $account->loadMissing('domain');
        $domainName = $account->domain?->name;
        if ($domainName !== null) {
            $this->engine->mailDeleteMailbox($domainName, $account->email);
        }
        $account->delete();

        SafeAuditLogger::info('hostvim.whmcs.email_delete', ['user_id' => $user->id], $request);

        return response()->json(['message' => 'deleted']);
    }

    public function ftpCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'username' => ['required', 'string', 'max:32'],
            'home_directory' => ['required', 'string', 'max:255'],
            'quota_mb' => ['nullable', 'integer', 'min:-1'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);
        $this->quota->ensureCanCreateFtpAccount($user);

        $password = $validated['password'] ?? null;
        if ($password === null || $password === '') {
            $password = Str::random(16);
        }

        $account = FtpAccount::create([
            'user_id' => $user->id,
            'domain_id' => $domain->id,
            'username' => $validated['username'],
            'password' => $password,
            'home_directory' => $validated['home_directory'],
            'quota_mb' => $validated['quota_mb'] ?? -1,
            'status' => 'active',
        ]);

        $engine = $this->engine->ftpProvision($domain->name, [
            'username' => $validated['username'],
            'home_directory' => $validated['home_directory'],
            'quota_mb' => $account->quota_mb,
            'password' => $password,
        ]);

        SafeAuditLogger::info('hostvim.whmcs.ftp_create', [
            'user_id' => $user->id,
            'domain' => $domain->name,
        ], $request);

        return response()->json([
            'account' => $account,
            'password_plain' => $password,
            'engine' => $engine,
        ], 201);
    }

    public function ftpDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'username' => ['required', 'string', 'max:32'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);
        $account = FtpAccount::query()
            ->where('user_id', $user->id)
            ->where('domain_id', $domain->id)
            ->where('username', $validated['username'])
            ->first();
        if ($account === null) {
            return response()->json(['message' => 'FTP hesabı bulunamadı.'], 404);
        }
        $this->engine->ftpDeleteAccount($domain->name, $account->username);
        $account->delete();

        SafeAuditLogger::info('hostvim.whmcs.ftp_delete', ['user_id' => $user->id], $request);

        return response()->json(['message' => 'deleted']);
    }

    public function databaseCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'in:mysql,postgresql'],
            'domain' => ['nullable', 'string', 'max:253'],
            'grant_host' => ['nullable', 'string', 'max:64'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domainId = null;
        if (! empty($validated['domain'])) {
            $domainId = $this->domainForUser($user, (string) $validated['domain'])->id;
        }
        $this->quota->ensureCanCreateDatabase($user);

        try {
            $result = $this->databaseService->create(
                $user,
                $validated['name'],
                $validated['type'] ?? 'mysql',
                $domainId,
                $validated['grant_host'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            report($e);

            return response()->json([
                'message' => __('databases.provision_failed').': '.$e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: __('databases.provision_failed')], 500);
        }

        SafeAuditLogger::info('hostvim.whmcs.database_create', ['user_id' => $user->id], $request);

        return response()->json([
            'database' => $result['database'],
            'password_plain' => $result['password_plain'],
        ], 201);
    }

    public function databaseDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'database_name' => ['required', 'string', 'max:128'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $db = Database::query()
            ->where('user_id', $user->id)
            ->where('name', $validated['database_name'])
            ->first();
        if ($db === null) {
            return response()->json(['message' => 'Veritabanı bulunamadı.'], 404);
        }
        try {
            $this->databaseService->delete($db);
        } catch (PDOException $e) {
            report($e);

            return response()->json([
                'message' => __('databases.provision_failed').': '.$e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage() ?: __('databases.provision_failed')], 500);
        }

        SafeAuditLogger::info('hostvim.whmcs.database_delete', ['user_id' => $user->id], $request);

        return response()->json(['message' => 'deleted']);
    }

    public function cronList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $jobs = CronJob::query()
            ->where('user_id', $user->id)
            ->where('is_system', false)
            ->orderBy('id')
            ->get(['id', 'schedule', 'command', 'description', 'status', 'engine_job_id']);

        return response()->json(['jobs' => $jobs]);
    }

    public function cronCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'schedule' => ['required', 'string', 'max:80', $this->cronScheduleRule()],
            'command' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $this->assertSafeCronCommand($validated['command']);
        $this->quota->ensureCanCreateCronJob($user);

        $job = CronJob::create([
            'user_id' => $user->id,
            'schedule' => $validated['schedule'],
            'command' => $validated['command'],
            'description' => $validated['description'] ?? null,
            'status' => 'active',
        ]);

        $engine = $this->engine->engineCronCreate([
            'schedule' => $job->schedule,
            'command' => $job->command,
            'user_id' => $job->user_id,
            'panel_job_id' => $job->id,
        ]);

        if (empty($engine['error']) && isset($engine['id']) && $engine['id'] !== '') {
            $job->update(['engine_job_id' => (string) $engine['id']]);
        }

        SafeAuditLogger::info('hostvim.whmcs.cron_create', ['user_id' => $user->id, 'job_id' => $job->id], $request);

        return response()->json([
            'job' => $job->fresh(),
            'engine' => $engine,
        ], 201);
    }

    public function cronDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'panel_job_id' => ['required', 'integer'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $job = CronJob::query()
            ->where('user_id', $user->id)
            ->where('id', $validated['panel_job_id'])
            ->where('is_system', false)
            ->first();
        if ($job === null) {
            return response()->json(['message' => 'Cron görevi bulunamadı.'], 404);
        }
        $eid = $job->engine_job_id;
        if ($eid === null || $eid === '') {
            $eid = (string) $job->id;
        }
        $job->delete();
        $engine = $this->engine->engineCronDelete($eid);

        SafeAuditLogger::info('hostvim.whmcs.cron_delete', ['user_id' => $user->id], $request);

        return response()->json(['message' => 'deleted', 'engine' => $engine]);
    }

    /** WHMCS ServiceSingleSignOn → tarayıcı yönlendirme URL’si. */
    public function ssoMint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if ($user === null || $user->status !== 'active') {
            return response()->json(['message' => 'Kullanıcı yok veya aktif değil.'], 404);
        }
        if ($user->isAdmin() || $user->isVendorOperator()) {
            return response()->json(['message' => 'Bu hesap türü için WHMCS SSO kullanılamaz.'], 422);
        }
        if ($user->two_factor_enabled && $user->two_factor_secret) {
            return response()->json([
                'message' => '2FA etkin hesaplar WHMCS SSO ile açılamaz; panelden giriş yapın.',
            ], 422);
        }

        $jti = (string) Str::uuid();
        Cache::put('whmcs_sso:'.$jti, ['user_id' => $user->id, 'admin' => false], now()->addMinutes(2));

        $redirectUrl = route('whmcs.sso.redirect', ['t' => $jti], true);

        SafeAuditLogger::info('hostvim.whmcs.sso_mint', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower(trim((string) $user->email))),
        ], $request);

        return response()->json([
            'redirect_url' => $redirectUrl,
            'expires_in' => 120,
        ]);
    }

    /** WHMCS AdminSingleSignOn — yalnızca admin rolü. */
    public function ssoMintAdmin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if ($user === null || $user->status !== 'active') {
            return response()->json(['message' => 'Kullanıcı yok veya aktif değil.'], 404);
        }
        if (! $user->isAdmin()) {
            return response()->json(['message' => 'WHMCS yönetici SSO yalnızca admin rolü için.'], 422);
        }
        if ($user->two_factor_enabled && $user->two_factor_secret) {
            return response()->json([
                'message' => '2FA etkin yönetici hesapları WHMCS SSO ile açılamaz.',
            ], 422);
        }

        $jti = (string) Str::uuid();
        Cache::put('whmcs_sso:'.$jti, ['user_id' => $user->id, 'admin' => true], now()->addMinutes(2));

        $redirectUrl = route('whmcs.sso.redirect', ['t' => $jti], true);

        SafeAuditLogger::info('hostvim.whmcs.sso_mint_admin', [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower(trim((string) $user->email))),
        ], $request);

        return response()->json([
            'redirect_url' => $redirectUrl,
            'expires_in' => 120,
        ]);
    }

    public function dnsList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        return response()->json([
            'records' => $domain->dnsRecords,
            'engine_preview' => $this->engine->dnsList($domain->name),
        ]);
    }

    public function dnsCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'type' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string'],
            'ttl' => ['nullable', 'integer', 'min:60'],
            'priority' => ['nullable', 'integer'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        $record = $domain->dnsRecords()->create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'value' => $validated['value'],
            'ttl' => $validated['ttl'] ?? null,
            'priority' => $validated['priority'] ?? null,
        ]);

        $enginePayload = array_merge([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'value' => $validated['value'],
            'ttl' => $validated['ttl'] ?? null,
            'priority' => $validated['priority'] ?? null,
        ], ['id' => (string) $record->id]);

        SafeAuditLogger::info('hostvim.whmcs.dns_create', ['user_id' => $user->id, 'domain' => $domain->name], $request);

        return response()->json([
            'record' => $record,
            'engine' => $this->engine->dnsCreate($domain->name, $enginePayload),
        ], 201);
    }

    /**
     * Toplu DNS kaydı (en fazla 200); tek transaction — engine hatasında geri alınır.
     *
     * @return list<array{type: string, name: string, value: string, ttl?: int|null, priority?: int|null}>
     */
    public function dnsImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.type' => ['required', 'string', 'max:10'],
            'records.*.name' => ['required', 'string', 'max:255'],
            'records.*.value' => ['required', 'string'],
            'records.*.ttl' => ['nullable', 'integer', 'min:60'],
            'records.*.priority' => ['nullable', 'integer'],
        ]);

        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        $created = [];

        try {
            DB::transaction(function () use ($domain, $validated, &$created, $request, $user): void {
                foreach ($validated['records'] as $row) {
                    $record = $domain->dnsRecords()->create([
                        'type' => $row['type'],
                        'name' => $row['name'],
                        'value' => $row['value'],
                        'ttl' => $row['ttl'] ?? null,
                        'priority' => $row['priority'] ?? null,
                    ]);

                    $enginePayload = [
                        'type' => $row['type'],
                        'name' => $row['name'],
                        'value' => $row['value'],
                        'ttl' => $row['ttl'] ?? null,
                        'priority' => $row['priority'] ?? null,
                        'id' => (string) $record->id,
                    ];

                    $engine = $this->engine->dnsCreate($domain->name, $enginePayload);
                    if (! empty($engine['error'])) {
                        throw new \RuntimeException((string) $engine['error']);
                    }

                    $created[] = $record;
                }

                SafeAuditLogger::info('hostvim.whmcs.dns_import', [
                    'user_id' => $user->id,
                    'domain' => $domain->name,
                    'count' => count($created),
                ], $request);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'DNS içe aktarma başarısız: '.$e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'DNS içe aktarma başarısız.'], 503);
        }

        return response()->json([
            'imported' => count($created),
            'records' => $created,
        ], 201);
    }

    /** BIND zone metni (A/AAAA/CNAME/MX/TXT); isteğe `replace_existing` ile mevcut kayıtları silip yazar. */
    public function dnsImportZone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'zone_text' => ['required', 'string', 'max:262144'],
            'replace_existing' => ['sometimes', 'boolean'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        $parsed = BindZoneTextParser::parse($validated['zone_text'], $domain->name);
        if ($parsed === []) {
            return response()->json([
                'message' => 'Zone metninden içe aktarılabilir kayıt bulunamadı (SOA/NS ve desteklenmeyen satırlar atlanır).',
            ], 422);
        }
        if (count($parsed) > 200) {
            return response()->json(['message' => 'En fazla 200 kayıt içe aktarılabilir.'], 422);
        }

        $created = [];

        try {
            DB::transaction(function () use ($request, $domain, $user, $parsed, &$created): void {
                if ($request->boolean('replace_existing')) {
                    foreach ($domain->dnsRecords()->get() as $r) {
                        $this->engine->dnsDeleteRecord($domain->name, (string) $r->id);
                        $r->delete();
                    }
                }

                foreach ($parsed as $row) {
                    $record = $domain->dnsRecords()->create([
                        'type' => $row['type'],
                        'name' => $row['name'],
                        'value' => $row['value'],
                        'ttl' => $row['ttl'] ?? null,
                        'priority' => $row['priority'] ?? null,
                    ]);
                    $enginePayload = [
                        'type' => $row['type'],
                        'name' => $row['name'],
                        'value' => $row['value'],
                        'ttl' => $row['ttl'] ?? null,
                        'priority' => $row['priority'] ?? null,
                        'id' => (string) $record->id,
                    ];
                    $engine = $this->engine->dnsCreate($domain->name, $enginePayload);
                    if (! empty($engine['error'])) {
                        throw new \RuntimeException((string) $engine['error']);
                    }
                    $created[] = $record;
                }

                SafeAuditLogger::info('hostvim.whmcs.dns_import_zone', [
                    'user_id' => $user->id,
                    'domain' => $domain->name,
                    'count' => count($created),
                    'replaced' => $request->boolean('replace_existing'),
                ], $request);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Zone içe aktarma: '.$e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Zone içe aktarma başarısız.'], 503);
        }

        return response()->json([
            'imported' => count($created),
            'records' => $created,
        ], 201);
    }

    public function dnsDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'record_id' => ['required', 'integer'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        $record = DnsRecord::query()
            ->whereKey($validated['record_id'])
            ->where('domain_id', $domain->id)
            ->first();
        if ($record === null) {
            return response()->json(['message' => 'DNS kaydı bulunamadı.'], 404);
        }

        $id = (string) $record->id;
        $record->delete();

        SafeAuditLogger::info('hostvim.whmcs.dns_delete', ['user_id' => $user->id], $request);

        return response()->json([
            'message' => 'deleted',
            'engine' => $this->engine->dnsDeleteRecord($domain->name, $id),
        ]);
    }

    public function emailForwarderCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'source' => ['required', 'string', 'max:128'],
            'destination' => ['required', 'email:rfc,dns', 'max:255'],
            'keep_copy' => ['sometimes', 'boolean'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        $source = strtolower(trim($validated['source']));
        if (! str_contains($source, '@')) {
            $source .= '@'.$domain->name;
        }
        if (! str_ends_with($source, '@'.$domain->name)) {
            return response()->json(['message' => __('email.forwarder_domain_mismatch')], 422);
        }

        $forwarder = EmailForwarder::create([
            'user_id' => $user->id,
            'domain_id' => $domain->id,
            'source' => $source,
            'destination' => strtolower(trim($validated['destination'])),
            'keep_copy' => (bool) ($validated['keep_copy'] ?? false),
        ]);

        $this->engine->mailAddForwarder($domain->name, [
            'source' => $forwarder->source,
            'destination' => $forwarder->destination,
        ]);

        SafeAuditLogger::info('hostvim.whmcs.forwarder_create', ['user_id' => $user->id], $request);

        return response()->json(['forwarder' => $forwarder], 201);
    }

    public function emailForwarderDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'forwarder_id' => ['required', 'integer'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $forwarder = EmailForwarder::query()
            ->where('user_id', $user->id)
            ->whereKey($validated['forwarder_id'])
            ->first();
        if ($forwarder === null) {
            return response()->json(['message' => 'Yönlendirici bulunamadı.'], 404);
        }
        $forwarder->loadMissing('domain');
        $domainName = $forwarder->domain?->name;
        if ($domainName !== null) {
            $this->engine->mailDeleteForwarder($domainName, $forwarder->source, $forwarder->destination);
        }
        $forwarder->delete();

        SafeAuditLogger::info('hostvim.whmcs.forwarder_delete', ['user_id' => $user->id], $request);

        return response()->json(['message' => 'deleted']);
    }

    public function databaseRotatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'database_name' => ['required', 'string', 'max:128'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $database = Database::query()
            ->where('user_id', $user->id)
            ->where('name', $validated['database_name'])
            ->first();
        if ($database === null) {
            return response()->json(['message' => 'Veritabanı bulunamadı.'], 404);
        }
        if (! in_array($database->type, ['mysql', 'postgresql'], true)) {
            return response()->json(['message' => __('databases.rotate_password_unsupported')], 422);
        }

        try {
            $result = $this->databaseService->rotatePassword($database);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (PDOException $e) {
            report($e);

            return response()->json([
                'message' => __('databases.provision_failed').': '.$e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: __('databases.provision_failed'),
            ], 500);
        }

        SafeAuditLogger::info('hostvim.whmcs.database_rotate', ['user_id' => $user->id], $request);

        return response()->json([
            'database' => $database->fresh(),
            'password_plain' => $result['password_plain'],
        ]);
    }

    public function sslIssue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'lets_encrypt_email' => ['nullable', 'email'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        try {
            $result = $this->sslIssue->issue(
                $user,
                $domain,
                $validated['lets_encrypt_email'] ?? null,
                config('hostvim.lets_encrypt_email') ?: null
            );
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        SafeAuditLogger::info('hostvim.whmcs.ssl_issue', [
            'user_id' => $user->id,
            'domain' => $domain->name,
            'ok' => (bool) ($result['ok'] ?? false),
        ], $request);

        if (! ($result['ok'] ?? false)) {
            return response()->json(array_filter([
                'message' => $result['message'] ?? null,
                'certificate' => $result['certificate'] ?? null,
                'engine' => $result['engine'] ?? null,
                'diagnostics' => $result['diagnostics'] ?? null,
            ], fn ($v) => $v !== null), (int) ($result['http_status'] ?? 422));
        }

        return response()->json([
            'message' => $result['message'] ?? __('ssl.issued'),
            'certificate' => $result['certificate'] ?? null,
            'engine' => $result['engine'] ?? null,
        ]);
    }

    public function sslRenew(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        $cert = $domain->sslCertificate;
        if ($cert === null) {
            return response()->json(['message' => __('ssl.missing')], 404);
        }

        try {
            $this->quota->ensureSslAllowed($user);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $engine = $this->engine->renewSSL($domain->name);
        SafeAuditLogger::info('hostvim.whmcs.ssl_renew', ['user_id' => $user->id, 'domain' => $domain->name], $request);

        if (! empty($engine['error'])) {
            return response()->json([
                'message' => $engine['error'],
                'certificate' => $cert->fresh(),
            ], 503);
        }

        return response()->json([
            'message' => __('ssl.renewed'),
            'engine' => $engine,
            'certificate' => $cert->fresh(),
        ]);
    }

    public function backupQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'domain' => ['required', 'string', 'max:253'],
            'type' => ['nullable', 'string', 'in:full,files,database'],
            'destination_id' => ['nullable', 'integer', 'exists:backup_destinations,id'],
        ]);
        $user = $this->userByEmail($validated['email']);
        $domain = $this->domainForUser($user, $validated['domain']);

        if (! empty($validated['destination_id'])) {
            $owns = BackupDestination::query()
                ->where('id', (int) $validated['destination_id'])
                ->where('user_id', $user->id)
                ->exists();
            if (! $owns) {
                return response()->json(['message' => 'Yedek hedefi bu müşteriye ait değil.'], 403);
            }
        }

        try {
            $this->quota->ensureCanQueueBackup($user);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $backup = Backup::create([
            'user_id' => $user->id,
            'domain_id' => $domain->id,
            'destination_id' => $validated['destination_id'] ?? null,
            'type' => $validated['type'] ?? 'full',
            'status' => 'pending',
        ]);

        $engine = $this->engine->queueBackup($domain->name, $backup->type, $backup->id);
        if (! empty($engine['error'])) {
            $backup->update(['status' => 'failed']);
            SafeAuditLogger::warning('hostvim.whmcs.backup_queue', [
                'user_id' => $user->id,
                'backup_id' => $backup->id,
                'error' => (string) $engine['error'],
            ], $request);

            return response()->json([
                'message' => (string) $engine['error'],
                'backup' => $backup->fresh(),
            ], 502);
        }

        $engineId = isset($engine['id']) ? (string) $engine['id'] : null;
        $engineStatus = is_string($engine['status'] ?? null) ? (string) $engine['status'] : '';
        $panelStatus = $engineStatus === 'completed' || $engineStatus === 'failed' ? $engineStatus : 'running';
        $update = [
            'status' => $panelStatus,
            'file_path' => $engine['path'] ?? null,
            'engine_backup_id' => $engineId,
        ];
        if (! empty($engine['size_bytes'])) {
            $update['size_mb'] = round(((float) $engine['size_bytes']) / 1048576, 4);
        }
        if ($panelStatus === 'completed') {
            $update['completed_at'] = now();
        }
        $backup->update($update);
        $backup = $backup->fresh();
        if ($panelStatus === 'completed' && $backup->destination_id) {
            $sync = app(BackupController::class)->syncToDestination($backup);
            if (empty($sync['ok'])) {
                SafeAuditLogger::warning('hostvim.whmcs.backup_queue_sync', [
                    'user_id' => $user->id,
                    'backup_id' => $backup->id,
                    'error' => (string) ($sync['error'] ?? 'sync failed'),
                ], $request);
            }
        }

        SafeAuditLogger::info('hostvim.whmcs.backup_queue', [
            'user_id' => $user->id,
            'backup_id' => $backup->id,
            'domain' => $domain->name,
        ], $request);

        return response()->json([
            'message' => __('backups.queued'),
            'backup' => $backup->fresh(),
            'engine' => $engine,
        ], 202);
    }

    private function userByEmail(string $email): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            throw new HttpResponseException(response()->json(['message' => 'Kullanıcı bulunamadı.'], 404));
        }

        return $user;
    }

    private function domainForUser(User $user, string $domainName): Domain
    {
        $host = strtolower(trim($domainName));
        $domain = Domain::query()->where('user_id', $user->id)->where('name', $host)->first();
        if ($domain === null) {
            throw new HttpResponseException(response()->json(['message' => 'Alan adı bu müşteriye ait değil.'], 404));
        }

        return $domain;
    }

    /**
     * @return \Closure(string, mixed, \Closure): void
     */
    private function cronScheduleRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value)) {
                $fail(__('cron.invalid_schedule'));

                return;
            }
            $parts = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
            if ($parts === false || count($parts) !== 5) {
                $fail(__('cron.invalid_schedule'));
            }
        };
    }

    private function assertSafeCronCommand(string $command): void
    {
        $cmd = trim($command);
        if ($cmd === '') {
            throw ValidationException::withMessages(['command' => 'Komut boş olamaz.']);
        }
        if (preg_match('/[;&|`><\n\r]/', $cmd) === 1) {
            throw ValidationException::withMessages([
                'command' => 'Güvenlik nedeniyle shell operatörleri kullanılamaz.',
            ]);
        }
        $parts = str_getcsv($cmd, ' ', '"', '\\');
        $argv = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $parts), static fn ($v) => $v !== ''));
        if ($argv === []) {
            throw ValidationException::withMessages(['command' => 'Komut çözümlenemedi.']);
        }
        $binary = $argv[0];
        if (! preg_match('/^[A-Za-z0-9_\/.\-]+$/', $binary)) {
            throw ValidationException::withMessages(['command' => 'Komut adı geçersiz.']);
        }
    }
}
