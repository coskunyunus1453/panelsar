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
            $name = strtolower(trim($name));
            $existing = Domain::query()->where('name', $name)->first();
            if ($existing) {
                if ($existing->user_id !== $user->id) {
                    abort(403, (string) __('domains.name_owned_elsewhere'));
                }

                // Aynı kullanıcı aynı alan adını tekrar isterse "self-heal / reprovision" yaklaşımı:
                // - active ise "zaten aktif" demek yerine idempotent createSite ile doğrula.
                // - suspended/pending/failed/deleting gibi durumlarda tekrar aktif hale getir.
                $resp = $this->engineApi->createSite($name, $user->id, $phpVersion, $serverType);
                if (! empty($resp['error'])) {
                    abort(503, (string) $resp['error']);
                }
                if (empty($resp['domain'])) {
                    abort(503, 'Engine yanıt vermedi; motor çalışıyor mu ve ENGINE_INTERNAL_KEY eşleşiyor mu kontrol edin.');
                }

                $fallbackRoot = rtrim((string) config('hostvim.hosting_web_root'), DIRECTORY_SEPARATOR);
                $documentRoot = (string) ($resp['document_root'] ?? $fallbackRoot.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'public_html');

                $existing->update([
                    'document_root' => $documentRoot,
                    'php_version' => $phpVersion,
                    'server_type' => $serverType,
                ]);

                // Mevcut kaydı “active” durumuna al ve engine'i (gerekirse suspend'tan) aktive et.
                $this->setPanelStatus($existing, 'active');

                return $existing->fresh();
            }

            $fallbackRoot = rtrim((string) config('hostvim.hosting_web_root'), DIRECTORY_SEPARATOR);
            $provisionalRoot = $fallbackRoot !== ''
                ? $fallbackRoot.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'public_html'
                : $name.DIRECTORY_SEPARATOR.'public_html';

            $domain = Domain::create([
                'user_id' => $user->id,
                'name' => $name,
                'document_root' => $provisionalRoot,
                'php_version' => $phpVersion,
                'server_type' => $serverType,
                'status' => 'active',
                'is_primary' => ! $user->domains()->exists(),
            ]);

            $resp = $this->engineApi->createSite($name, $user->id, $phpVersion, $serverType);
            if (! empty($resp['error'])) {
                abort(503, (string) $resp['error']);
            }
            if (empty($resp['domain'])) {
                abort(503, 'Engine yanıt vermedi; motor çalışıyor mu ve ENGINE_INTERNAL_KEY eşleşiyor mu kontrol edin.');
            }

            $documentRoot = (string) ($resp['document_root'] ?? $provisionalRoot);
            if ($documentRoot !== $provisionalRoot) {
                $domain->update(['document_root' => $documentRoot]);
            }

            return $domain->fresh();
        });
    }

    public function delete(Domain $domain): void
    {
        DB::transaction(function () use ($domain) {
            $domain->loadMissing(['databases', 'ftpAccounts', 'siteSubdomains']);
            $domain->update(['status' => 'deleting']);

            foreach ($domain->ftpAccounts as $ftp) {
                $ftpDel = $this->engineApi->ftpDeleteAccount($domain->name, $ftp->username);
                if (! empty($ftpDel['error']) && ! $this->isIgnorableDeleteError((string) $ftpDel['error'])) {
                    abort(503, (string) $ftpDel['error']);
                }
                $ftp->delete();
            }

            $mailDel = $this->engineApi->mailDeleteDomainState($domain->name);
            if (! empty($mailDel['error']) && ! $this->isIgnorableDeleteError((string) $mailDel['error'])) {
                abort(503, (string) $mailDel['error']);
            }

            foreach ($domain->siteSubdomains as $sub) {
                $rm = $this->engineApi->siteRemoveSubdomain($domain->name, $sub->path_segment);
                if (! empty($rm['error']) && ! $this->isIgnorableDeleteError((string) $rm['error'])) {
                    abort(503, (string) $rm['error']);
                }
            }

            $del = $this->engineApi->deleteSite($domain->name);
            if (! empty($del['error']) && ! $this->isIgnorableDeleteError((string) $del['error'])) {
                abort(503, (string) $del['error']);
            }

            $dbService = app(DatabaseService::class);
            foreach ($domain->databases as $db) {
                $dbService->delete($db);
            }
            $domain->emailAccounts()->delete();
            $domain->dnsRecords()->delete();
            $domain->sslCertificate()?->delete();
            $domain->backups()->delete();
            $domain->delete();
        });
    }

    public function switchPhpVersion(Domain $domain, string $version): void
    {
        DB::transaction(function () use ($domain, $version): void {
            $resp = $this->engineApi->createSite($domain->name, $domain->user_id, $version, $domain->server_type ?? 'nginx');
            if (! empty($resp['error'])) {
                abort(503, (string) $resp['error']);
            }
            $domain->update(['php_version' => $version]);
        });
    }

    public function switchServerType(Domain $domain, string $serverType): void
    {
        $serverType = in_array($serverType, ['nginx', 'apache', 'openlitespeed'], true) ? $serverType : 'nginx';
        DB::transaction(function () use ($domain, $serverType): void {
            $resp = $this->engineApi->createSite(
                $domain->name,
                (int) $domain->user_id,
                (string) ($domain->php_version ?? '8.2'),
                $serverType
            );
            if (! empty($resp['error'])) {
                abort(503, (string) $resp['error']);
            }
            $domain->update(['server_type' => $serverType]);
        });
    }

    /**
     * Document root varyantı:
     * - root   => <webroot>/<domain>/public_html
     * - public => <webroot>/<domain>/public_html/public (Laravel gibi projeler için)
     *
     * Engine tarafında güvenli path doğrulaması yapılır ve vhost/pool yeniden uygulanır.
     *
     * @return array<string, mixed>
     */
    public function setDocumentRootVariant(Domain $domain, string $variant): array
    {
        $variant = in_array($variant, ['root', 'public'], true) ? $variant : 'root';

        return DB::transaction(function () use ($domain, $variant): array {
            $resp = $this->engineApi->setSiteDocumentRoot($domain->name, $variant);
            if (! empty($resp['error'])) {
                return $resp;
            }
            if (! empty($resp['document_root'])) {
                $domain->update(['document_root' => (string) $resp['document_root']]);
            }
            return $resp;
        });
    }

    public function setPanelStatus(Domain $domain, string $status): void
    {
        $status = $status === 'suspended' ? 'suspended' : 'active';

        DB::transaction(function () use ($domain, $status): void {
            if ($status === 'suspended') {
                if (! in_array($domain->status, ['suspended', 'deleting'], true)) {
                    $resp = $this->engineApi->suspendSite($domain->name);
                    if (! empty($resp['error'])) {
                        abort(503, (string) $resp['error']);
                    }
                }
                $domain->update(['status' => 'suspended']);

                return;
            }

            if ($domain->status === 'suspended') {
                $resp = $this->engineApi->activateSite($domain->name);
                if (! empty($resp['error'])) {
                    abort(503, (string) $resp['error']);
                }
            }
            $domain->update(['status' => 'active']);
        });
    }

    private function isIgnorableDeleteError(string $error): bool
    {
        $e = strtolower(trim($error));
        if ($e === '') {
            return false;
        }

        // Silme idempotent olsun: site zaten yoksa panel tarafı temizliği devam edebilsin.
        return str_contains($e, 'not found')
            || str_contains($e, 'site not found')
            || str_contains($e, 'no such file')
            || str_contains($e, 'does not exist');
    }
}
