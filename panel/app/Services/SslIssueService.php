<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SslCertificate;
use App\Models\User;
use Illuminate\Support\Str;

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

        $diagnostics = $this->preflightDiagnostics($domain);

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
                'diagnostics' => $diagnostics,
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
                'diagnostics' => $diagnostics,
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

    /**
     * SSL issue öncesi hızlı teşhis (engine dışı).
     *
     * @return list<array{key: string, ok: bool, message: string}>
     */
    private function preflightDiagnostics(Domain $domain): array
    {
        $rows = [];

        $host = trim((string) $domain->name);
        $docroot = trim((string) ($domain->document_root ?? ''));

        // DNS resolve
        $ip = $host !== '' ? gethostbyname($host) : '';
        $dnsOk = $ip !== '' && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP);
        $rows[] = [
            'key' => 'dns',
            'ok' => (bool) $dnsOk,
            'message' => $dnsOk ? ('DNS OK: '.$ip) : 'DNS resolve basarisiz (A/AAAA kaydi yok veya domain hatali)',
        ];

        // TCP reachability
        foreach ([80, 443] as $port) {
            $ok = false;
            if ($host !== '') {
                $errno = 0;
                $errstr = '';
                $sock = @fsockopen($host, $port, $errno, $errstr, 2.0);
                if (is_resource($sock)) {
                    $ok = true;
                    fclose($sock);
                }
            }
            $rows[] = [
                'key' => 'tcp_'.$port,
                'ok' => $ok,
                'message' => $ok ? ("Port {$port} ulasilabilir") : ("Port {$port} ulasilamiyor (firewall/DNS/proxy)"),
            ];
        }

        // Docroot + ACME path
        $docOk = $docroot !== '' && is_dir($docroot) && is_writable($docroot);
        $rows[] = [
            'key' => 'docroot',
            'ok' => $docOk,
            'message' => $docOk ? 'Document root yazilabilir' : 'Document root yazilabilir degil (izin/yol)',
        ];

        if ($docroot !== '' && is_dir($docroot)) {
            $acme = rtrim($docroot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.well-known'.DIRECTORY_SEPARATOR.'acme-challenge';
            $ok = true;
            if (! is_dir($acme)) {
                $ok = @mkdir($acme, 0755, true);
            }
            if ($ok) {
                $probe = $acme.DIRECTORY_SEPARATOR.'hostvim_acme_'.Str::random(8).'.txt';
                $ok = @file_put_contents($probe, 'ok') !== false;
                @unlink($probe);
            }
            $rows[] = [
                'key' => 'acme_path',
                'ok' => $ok,
                'message' => $ok ? 'ACME challenge yolu hazir' : 'ACME challenge yolu yazilamiyor (izin/owner)',
            ];
        } else {
            $rows[] = [
                'key' => 'acme_path',
                'ok' => false,
                'message' => 'ACME challenge yolu kontrol edilemedi (docroot yok)',
            ];
        }

        return $rows;
    }
}
