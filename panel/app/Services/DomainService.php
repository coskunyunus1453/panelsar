<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DomainService
{
    public function __construct(
        private EngineApiService $engineApi,
    ) {}

    public function create(User $user, string $name, string $phpVersion, string $serverType): Domain
    {
        return DB::transaction(function () use ($user, $name, $phpVersion, $serverType) {
            $resp = $this->engineApi->createSite($name, $user->id, $phpVersion, $serverType);
            if (! empty($resp['error'])) {
                abort(503, (string) $resp['error']);
            }
            if (empty($resp['domain'])) {
                abort(503, 'Engine yanıt vermedi; motor çalışıyor mu ve ENGINE_INTERNAL_KEY eşleşiyor mu kontrol edin.');
            }

            $fallbackRoot = rtrim((string) config('panelsar.hosting_web_root'), DIRECTORY_SEPARATOR);
            $documentRoot = (string) ($resp['document_root'] ?? $fallbackRoot.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'public_html');

            $domain = Domain::create([
                'user_id' => $user->id,
                'name' => $name,
                'document_root' => $documentRoot,
                'php_version' => $phpVersion,
                'server_type' => $serverType,
                'status' => 'active',
                'is_primary' => ! $user->domains()->exists(),
            ]);

            return $domain;
        });
    }

    public function delete(Domain $domain): void
    {
        DB::transaction(function () use ($domain) {
            $del = $this->engineApi->deleteSite($domain->name);
            if (! empty($del['error'])) {
                abort(503, (string) $del['error']);
            }

            $domain->loadMissing('databases');
            $dbService = app(DatabaseService::class);
            foreach ($domain->databases as $db) {
                $dbService->delete($db);
            }
            $domain->emailAccounts()->delete();
            $domain->dnsRecords()->delete();
            $domain->sslCertificate()?->delete();
            $domain->delete();
        });
    }

    public function switchPhpVersion(Domain $domain, string $version): void
    {
        $resp = $this->engineApi->createSite($domain->name, $domain->user_id, $version, $domain->server_type ?? 'nginx');
        if (! empty($resp['error'])) {
            abort(503, (string) $resp['error']);
        }
        $domain->update(['php_version' => $version]);
    }
}
