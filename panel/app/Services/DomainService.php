<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\EmailForwarder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DomainService
{
    public function __construct(
        private EngineApiService $engineApi,
        private HostnameReservationService $hostnames,
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
    public function setDocumentRootVariant(
        Domain $domain,
        ?string $variant = null,
        ?string $profile = null,
        ?string $customPath = null,
    ): array
    {
        return DB::transaction(function () use ($domain, $variant, $profile, $customPath): array {
            $resp = $this->engineApi->setSiteDocumentRoot($domain->name, $variant, $profile, $customPath);
            if (! empty($resp['error'])) {
                return $resp;
            }
            if (! empty($resp['document_root'])) {
                $domain->update(['document_root' => (string) $resp['document_root']]);
            }
            return $resp;
        });
    }

    /**
     * WHMCS ChangeDomain: motor + panel kayıtlarında birincil FQDN değişimi (SSL sıfırlanır, LE yeniden gerekir).
     */
    public function renamePrimarySite(User $user, Domain $domain, string $newName): Domain
    {
        $newName = strtolower(trim($newName));
        $oldName = strtolower(trim((string) $domain->name));
        if ($newName === $oldName) {
            return $domain->fresh();
        }
        if ((int) $domain->user_id !== (int) $user->id) {
            abort(403, 'Bu site bu kullanıcıya ait değil.');
        }
        if (Domain::query()->where('name', $newName)->where('id', '!=', $domain->id)->exists()) {
            throw ValidationException::withMessages([
                'domain' => [__('domains.name_owned_elsewhere')],
            ]);
        }
        $this->hostnames->assertPrimaryDomainForUser($user, $newName);

        return DB::transaction(function () use ($domain, $newName, $oldName): Domain {
            $domain->load(['emailAccounts', 'siteSubdomains', 'siteDomainAliases']);

            $engine = $this->engineApi->renameSite($oldName, $newName);
            if (! empty($engine['error'])) {
                abort(503, (string) $engine['error']);
            }

            $oldRoot = (string) $domain->document_root;
            $sep = DIRECTORY_SEPARATOR;
            $newRoot = $oldRoot;
            if (str_contains($oldRoot, $sep.$oldName.$sep)) {
                $newRoot = str_replace($sep.$oldName.$sep, $sep.$newName.$sep, $oldRoot);
            } elseif (str_contains($oldRoot, '/'.$oldName.'/')) {
                $newRoot = str_replace('/'.$oldName.'/', '/'.$newName.'/', $oldRoot);
            }

            $domain->update([
                'name' => $newName,
                'document_root' => $newRoot,
                'ssl_enabled' => false,
                'ssl_expiry' => null,
            ]);
            $domain->sslCertificate()?->delete();

            foreach ($domain->emailAccounts as $acct) {
                $acct->update(['email' => $this->replaceEmailHost((string) $acct->email, $oldName, $newName)]);
            }

            foreach (EmailForwarder::query()->where('domain_id', $domain->id)->get() as $fw) {
                $fw->update([
                    'source' => $this->replaceForwarderLocal((string) $fw->source, $oldName, $newName),
                    'destination' => $this->replaceEmailHostIfLocal((string) $fw->destination, $oldName, $newName),
                ]);
            }

            foreach ($domain->siteSubdomains as $sub) {
                $doc = $sub->document_root;
                $newDoc = ($doc !== null && $doc !== '')
                    ? $this->patchDocumentRootPath((string) $doc, $oldName, $newName)
                    : $doc;
                $sub->update([
                    'hostname' => $this->replaceHostSuffix((string) $sub->hostname, $oldName, $newName),
                    'document_root' => $newDoc,
                ]);
            }

            foreach ($domain->siteDomainAliases as $alias) {
                $alias->update([
                    'hostname' => $this->replaceHostSuffix((string) $alias->hostname, $oldName, $newName),
                ]);
            }

            return $domain->fresh();
        });
    }

    private function replaceHostSuffix(string $host, string $oldFqdn, string $newFqdn): string
    {
        $h = strtolower(trim($host));
        $o = strtolower(trim($oldFqdn));
        $n = strtolower(trim($newFqdn));
        if ($h === $o) {
            return $n;
        }
        $suf = '.'.$o;

        return str_ends_with($h, $suf)
            ? substr($h, 0, -strlen($suf)).'.'.$n
            : $h;
    }

    private function replaceEmailHost(string $email, string $oldFqdn, string $newFqdn): string
    {
        $e = strtolower(trim($email));
        $needle = '@'.strtolower($oldFqdn);

        return str_ends_with($e, $needle)
            ? substr($e, 0, -strlen($needle)).'@'.strtolower($newFqdn)
            : $email;
    }

    /** Yönlendirici kaynağı: tam adres veya sadece yerel parça (@eski eklenmiş). */
    private function replaceForwarderLocal(string $source, string $oldFqdn, string $newFqdn): string
    {
        $s = trim($source);
        if (str_contains($s, '@')) {
            return $this->replaceEmailHost($s, $oldFqdn, $newFqdn);
        }

        return $s.'@'.strtolower($newFqdn);
    }

    private function replaceEmailHostIfLocal(string $email, string $oldFqdn, string $newFqdn): string
    {
        $e = strtolower(trim($email));
        if (! str_contains($e, '@')) {
            return $email;
        }

        return $this->replaceEmailHost($e, $oldFqdn, $newFqdn);
    }

    private function patchDocumentRootPath(string $path, string $oldFqdn, string $newFqdn): string
    {
        $sep = DIRECTORY_SEPARATOR;

        return str_contains($path, $sep.$oldFqdn.$sep)
            ? str_replace($sep.$oldFqdn.$sep, $sep.$newFqdn.$sep, $path)
            : (str_contains($path, '/'.$oldFqdn.'/')
                ? str_replace('/'.$oldFqdn.'/', '/'.$newFqdn.'/', $path)
                : $path);
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
