<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SiteDomainAlias;
use App\Models\SiteSubdomain;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Motor nginx.DomainSafe ile uyumlu FQDN doğrulama ve çakışma kontrolü (primary / alt alan / alias).
 */
class HostnameReservationService
{
    /** @see engine/internal/nginx/vhost.go domainSafe */
    private const DOMAIN_SAFE = '/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*$/';

    public function assertEngineSafeFqdn(string $fqdn, string $attribute = 'hostname'): void
    {
        $fqdn = strtolower(trim($fqdn));
        if ($fqdn === '' || str_contains($fqdn, '..') || str_contains($fqdn, '*')) {
            throw ValidationException::withMessages([$attribute => [__('sites.invalid_hostname')]]);
        }
        if (preg_match(self::DOMAIN_SAFE, $fqdn) !== 1) {
            throw ValidationException::withMessages([$attribute => [__('sites.invalid_hostname')]]);
        }
    }

    /**
     * Birincil site: FQDN olmalı; başka kullanıcının primary’si veya herhangi bir alt alan/alias kaydı ile çakışmamalı.
     * Aynı kullanıcının mevcut sitesi ise DomainService idempotent akışına bırakılır.
     */
    public function assertPrimaryDomainForUser(User $user, string $domain): void
    {
        $this->assertEngineSafeFqdn($domain, 'domain');
        $d = strtolower(trim($domain));
        if (substr_count($d, '.') < 1) {
            throw ValidationException::withMessages(['domain' => [__('sites.primary_must_be_fqdn')]]);
        }
        $existing = Domain::query()->where('name', $d)->first();
        if ($existing !== null && (int) $existing->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['domain' => [__('domains.name_owned_elsewhere')]]);
        }
        if ($existing !== null) {
            return;
        }
        if (SiteSubdomain::query()->where('hostname', $d)->exists()
            || SiteDomainAlias::query()->where('hostname', $d)->exists()) {
            throw ValidationException::withMessages(['domain' => [__('sites.hostname_already_taken')]]);
        }
    }

    public function isGloballyTaken(string $hostname): bool
    {
        $h = strtolower(trim($hostname));

        return Domain::query()->where('name', $h)->exists()
            || SiteSubdomain::query()->where('hostname', $h)->exists()
            || SiteDomainAlias::query()->where('hostname', $h)->exists();
    }

    /**
     * Alt alan: üst sitenin FQDN’i ile bitmeli; path_segment tek etiket ise otomatik çıkarılabilir.
     *
     * @return non-empty-string
     */
    public function resolveSubdomainPathSegment(string $parentFqdn, string $subdomainHostname, ?string $pathSegment): string
    {
        $parent = strtolower(trim($parentFqdn));
        $h = strtolower(trim($subdomainHostname));
        $suffix = '.'.$parent;
        if (! str_ends_with($h, $suffix)) {
            throw ValidationException::withMessages(['hostname' => [__('sites.subdomain_must_end_with_parent')]]);
        }
        $rest = substr($h, 0, -strlen($suffix));
        if ($rest === '') {
            throw ValidationException::withMessages(['hostname' => [__('sites.subdomain_invalid_prefix')]]);
        }

        $ps = $pathSegment !== null ? strtolower(trim($pathSegment)) : '';
        if ($ps !== '') {
            if (preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $ps) !== 1) {
                throw ValidationException::withMessages(['path_segment' => [__('sites.invalid_path_segment')]]);
            }

            return $ps;
        }

        if (str_contains($rest, '.')) {
            throw ValidationException::withMessages([
                'path_segment' => [__('sites.path_segment_required_for_nested')],
            ]);
        }

        return $rest;
    }

    public function assertAliasAllowed(Domain $site, string $aliasHostname): void
    {
        $this->assertEngineSafeFqdn($aliasHostname, 'hostname');
        $h = strtolower(trim($aliasHostname));
        if ($h === strtolower($site->name)) {
            throw ValidationException::withMessages(['hostname' => [__('sites.alias_same_as_primary')]]);
        }
        if ($this->isGloballyTaken($h)) {
            throw ValidationException::withMessages(['hostname' => [__('sites.hostname_already_taken')]]);
        }
    }
}
