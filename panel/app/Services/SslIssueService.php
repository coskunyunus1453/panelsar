<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SslCertificate;
use App\Models\User;

class SslIssueService
{
    public function __construct(
        private EngineApiService $engine,
        private HostingQuotaService $quota,
    ) {}

    /**
     * Let’s Encrypt DV sertifikası — SslController ve SiteController ortak kullanımı.
     *
     * @return array{
     *     ok: bool,
     *     http_status: int,
     *     message?: string,
     *     certificate?: SslCertificate|null,
     *     engine?: array<string, mixed>
     * }
     */
    public function issue(User $user, Domain $domain, ?string $emailFromRequest, ?string $configFallbackEmail): array
    {
        $this->quota->ensureSslAllowed($user);

        $email = $emailFromRequest;
        if ($email === null || $email === '') {
            $email = $configFallbackEmail ?: null;
        }
        if ($email === null || $email === '') {
            $email = $user->email;
        }
        if ($email === null || $email === '') {
            return [
                'ok' => false,
                'http_status' => 422,
                'message' => (string) __('ssl.email_required'),
            ];
        }

        $cert = SslCertificate::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'provider' => 'letsencrypt',
                'type' => 'dv',
                'status' => 'pending',
                'auto_renew' => true,
            ]
        );

        // Eski vhost şablonları ACME yolunu engelliyor olabilir; issue öncesi conf'u tekrar uygula.
        $activate = $this->engine->activateSite($domain->name);
        if (! empty($activate['error'])) {
            $cert->update(['status' => 'failed']);
            return [
                'ok' => false,
                'http_status' => 503,
                'message' => (string) $activate['error'],
                'certificate' => $cert->fresh(),
                'engine' => $activate,
            ];
        }

        $engine = $this->engine->issueSSL($domain->name, is_string($email) ? $email : null);
        if (! empty($engine['error'])) {
            $cert->update(['status' => 'failed']);
            $domain->update(['ssl_enabled' => false, 'ssl_expiry' => null]);

            return [
                'ok' => false,
                'http_status' => 503,
                'message' => (string) $engine['error'],
                'certificate' => $cert->fresh(),
                'engine' => $engine,
            ];
        }

        $cert->update(['status' => 'active', 'issued_at' => now(), 'expires_at' => now()->addDays(90)]);
        $domain->update([
            'ssl_enabled' => true,
            'ssl_expiry' => $cert->expires_at,
        ]);

        return [
            'ok' => true,
            'http_status' => 200,
            'message' => (string) __('ssl.issued'),
            'certificate' => $cert->fresh(),
            'engine' => $engine,
        ];
    }
}
