<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\PanelSetting;
use App\Services\EngineApiService;
use App\Services\OutboundMailConfigurator;
use App\Services\WhiteLabelBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;

class OutboundMailSettingsController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
        private WhiteLabelBrandingService $whiteLabelBranding,
    ) {}

    public function show(): JsonResponse
    {
        $rows = PanelSetting::query()
            ->where('key', 'like', 'outbound_mail.%')
            ->pluck('value', 'key');

        $pass = $rows->get('outbound_mail.smtp_password');

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $defaultHost = is_string($appHost) && $appHost !== '' ? $appHost : '127.0.0.1';

        return response()->json([
            'outbound_mail_persisted' => $rows->has('outbound_mail.driver'),
            'driver' => $rows->get('outbound_mail.driver', config('mail.default', 'log')),
            'smtp_host' => $rows->get('outbound_mail.smtp_host', ''),
            'smtp_port' => (int) ($rows->get('outbound_mail.smtp_port', 587) ?: 587),
            'smtp_username' => $rows->get('outbound_mail.smtp_username', ''),
            'smtp_password_set' => is_string($pass) && $pass !== '',
            'smtp_encryption' => $rows->get('outbound_mail.smtp_encryption', '') ?: '',
            'from_address' => $rows->get('outbound_mail.from_address', config('mail.from.address')),
            'from_name' => $rows->get('outbound_mail.from_name', config('mail.from.name')),
            'smtp_recommended_host' => $defaultHost,
            'smtp_recommended_port' => 587,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'string', Rule::in(['smtp', 'sendmail', 'log'])],
            'smtp_host' => ['required_if:driver,smtp', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['required_if:driver,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:500'],
            'smtp_encryption' => ['nullable', 'string', Rule::in(['', 'tls', 'ssl'])],
            'clear_smtp_password' => ['sometimes', 'boolean'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:120'],
        ]);

        $set = function (string $key, ?string $value): void {
            PanelSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value ?? '']
            );
        };

        $set('outbound_mail.driver', $validated['driver']);
        $set('outbound_mail.smtp_host', $validated['smtp_host'] ?? '');
        $set('outbound_mail.smtp_port', isset($validated['smtp_port']) ? (string) $validated['smtp_port'] : '587');
        $set('outbound_mail.smtp_username', $validated['smtp_username'] ?? '');
        $enc = $validated['smtp_encryption'] ?? '';
        $set('outbound_mail.smtp_encryption', $enc === '' ? '' : $enc);
        $set('outbound_mail.from_address', $validated['from_address']);
        $set('outbound_mail.from_name', $validated['from_name']);

        if ($request->boolean('clear_smtp_password')) {
            PanelSetting::query()->where('key', 'outbound_mail.smtp_password')->delete();
        } elseif (! empty($validated['smtp_password'])) {
            $set('outbound_mail.smtp_password', encrypt($validated['smtp_password']));
        }

        OutboundMailConfigurator::apply();

        return $this->show();
    }

    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'to' => ['nullable', 'email', 'max:255'],
        ]);

        if (! PanelSetting::query()->where('key', 'outbound_mail.driver')->exists()) {
            return response()->json([
                'message' => __('stack.mail_test_requires_saved_settings'),
            ], 422);
        }

        OutboundMailConfigurator::apply();

        $default = (string) config('mail.default', 'log');
        if (! in_array($default, ['smtp', 'sendmail'], true)) {
            return response()->json([
                'message' => __('stack.mail_test_requires_real_transport', ['driver' => $default]),
            ], 422);
        }

        if ($default === 'smtp') {
            $host = (string) PanelSetting::query()
                ->where('key', 'outbound_mail.smtp_host')
                ->value('value');
            if (trim($host) === '') {
                return response()->json([
                    'message' => __('stack.mail_test_smtp_host_required'),
                ], 422);
            }
        }

        $to = $request->input('to') ?: $request->user()->email;

        try {
            $body = $this->whiteLabelBranding->appendMailFooter(
                $request->user(),
                (string) __('stack.mail_test_body')
            );
            Mail::raw($body, function ($message) use ($to): void {
                $message->to($to)->subject(__('stack.mail_test_subject'));
            });
        } catch (\Throwable $e) {
            Log::warning('Outbound mail test failed', [
                'user_id' => $request->user()?->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => app()->isProduction()
                    ? __('stack.mail_test_failed_generic')
                    : $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => __('stack.mail_test_sent', ['email' => $to])]);
    }

    public function diagnostics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        $host = trim((string) ($validated['smtp_host'] ?? ''));
        $port = (int) ($validated['smtp_port'] ?? 587);
        if ($host === '') {
            return response()->json([
                'ok' => false,
                'message' => 'SMTP host zorunlu.',
            ], 422);
        }

        $ips = @gethostbynamel($host);
        $resolved = is_array($ips) && count($ips) > 0 && $ips[0] !== $host;

        $connOk = false;
        $connErr = null;
        if ($resolved) {
            $errno = 0;
            $errstr = '';
            $sock = @fsockopen($host, $port, $errno, $errstr, 5.0);
            if (is_resource($sock)) {
                $connOk = true;
                fclose($sock);
            } else {
                $connErr = trim($errstr) !== '' ? $errstr : ('errno: '.$errno);
            }
        }

        $hint = null;
        if (! $resolved) {
            $hint = 'Host DNS kaydı bulunamadı. Önce A/AAAA kaydı ekleyin veya doğru SMTP host girin.';
        } elseif (! $connOk) {
            $hint = 'DNS var ama port erişimi yok. SMTP servisi (Postfix/Exim) ve firewall/NAT kontrol edilmeli.';
        } elseif (Str::startsWith($host, 'mail.')) {
            $hint = 'mail.<domain> hostu erişilebilir görünüyor.';
        }

        return response()->json([
            'ok' => $resolved && $connOk,
            'host' => $host,
            'port' => $port,
            'dns_resolved' => $resolved,
            'ips' => $resolved ? $ips : [],
            'port_open' => $connOk,
            'connection_error' => $connErr,
            'hint' => $hint,
        ]);
    }

    public function wizardChecks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ]);

        $domain = strtolower(trim((string) $validated['domain']));
        $mailHost = 'mail.'.$domain;
        $webmailHost = 'webmail.'.$domain;

        $aDomain = @gethostbynamel($domain);
        $aMail = @gethostbynamel($mailHost);
        $aWebmail = @gethostbynamel($webmailHost);

        $domainDnsOk = is_array($aDomain) && count($aDomain) > 0 && $aDomain[0] !== $domain;
        $mailDnsOk = is_array($aMail) && count($aMail) > 0 && $aMail[0] !== $mailHost;
        $webmailDnsOk = is_array($aWebmail) && count($aWebmail) > 0 && $aWebmail[0] !== $webmailHost;

        $smtp587 = $this->probePort($mailHost, 587, 4.0);
        $smtp465 = $this->probePort($mailHost, 465, 4.0);
        $imap993 = $this->probePort($mailHost, 993, 4.0);
        $imap143 = $this->probePort($mailHost, 143, 4.0);
        $web443 = $this->probePort($webmailHost, 443, 4.0);

        $checks = [
            [
                'key' => 'domain_dns',
                'label' => 'Domain DNS',
                'ok' => $domainDnsOk,
                'detail' => $domainDnsOk ? 'Domain DNS kaydı var.' : 'Domain DNS kaydı bulunamadı.',
            ],
            [
                'key' => 'mail_dns',
                'label' => 'Mail Host DNS',
                'ok' => $mailDnsOk,
                'detail' => $mailDnsOk ? 'mail alt alan adı çözümleniyor.' : "DNS'te {$mailHost} kaydı yok.",
            ],
            [
                'key' => 'smtp_port',
                'label' => 'SMTP Port (587/465)',
                'ok' => $smtp587['ok'] || $smtp465['ok'],
                'detail' => $smtp587['ok']
                    ? 'SMTP 587 erişilebilir.'
                    : ($smtp465['ok'] ? 'SMTP 465 erişilebilir.' : 'SMTP portları erişilemiyor.'),
            ],
            [
                'key' => 'imap_port',
                'label' => 'IMAP Port (993/143)',
                'ok' => $imap993['ok'] || $imap143['ok'],
                'detail' => $imap993['ok']
                    ? 'IMAP 993 erişilebilir.'
                    : ($imap143['ok'] ? 'IMAP 143 erişilebilir.' : 'IMAP portları erişilemiyor.'),
            ],
            [
                'key' => 'webmail_dns',
                'label' => 'Webmail DNS',
                'ok' => $webmailDnsOk,
                'detail' => $webmailDnsOk ? 'webmail alt alan adı çözümleniyor.' : "DNS'te {$webmailHost} kaydı yok.",
            ],
            [
                'key' => 'webmail_https',
                'label' => 'Webmail HTTPS (443)',
                'ok' => $web443['ok'],
                'detail' => $web443['ok'] ? '443 portuna erişim var.' : '443 portuna erişim yok veya servis dinlemiyor.',
            ],
        ];

        $allOk = ! in_array(false, array_column($checks, 'ok'), true);
        $recommended = [
            'smtp_host' => $mailHost,
            'smtp_port' => $smtp587['ok'] ? 587 : 465,
            'smtp_encryption' => $smtp587['ok'] ? 'tls' : 'ssl',
            'imap_host' => $mailHost,
            'imap_port' => $imap993['ok'] ? 993 : 143,
            'webmail_url' => $webmailDnsOk ? "https://{$webmailHost}" : null,
        ];

        $ipGuess = null;
        if ($domainDnsOk && is_array($aDomain) && count($aDomain) > 0) {
            $ipGuess = (string) $aDomain[0];
        }

        $dnsSuggestions = [
            [
                'type' => 'A',
                'name' => 'mail',
                'value' => $ipGuess,
                'required' => ! $mailDnsOk,
            ],
            [
                'type' => 'A',
                'name' => 'webmail',
                'value' => $ipGuess,
                'required' => ! $webmailDnsOk,
            ],
            [
                'type' => 'MX',
                'name' => '@',
                'value' => $mailHost,
                'priority' => 10,
                'required' => true,
            ],
            [
                'type' => 'TXT',
                'name' => '@',
                'value' => 'v=spf1 mx a ~all',
                'required' => true,
            ],
            [
                'type' => 'TXT',
                'name' => '_dmarc',
                'value' => 'v=DMARC1; p=none; rua=mailto:postmaster@'.$domain,
                'required' => true,
            ],
        ];

        return response()->json([
            'ok' => $allOk,
            'domain' => $domain,
            'checks' => $checks,
            'recommended' => $recommended,
            'dns_suggestions' => $dnsSuggestions,
            'raw' => [
                'domain_ips' => $domainDnsOk ? $aDomain : [],
                'mail_ips' => $mailDnsOk ? $aMail : [],
                'webmail_ips' => $webmailDnsOk ? $aWebmail : [],
                'smtp_587' => $smtp587,
                'smtp_465' => $smtp465,
                'imap_993' => $imap993,
                'imap_143' => $imap143,
                'web_443' => $web443,
            ],
        ]);
    }

    public function wizardApplyDns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ]);
        $domainName = strtolower(trim((string) $validated['domain']));

        $domain = Domain::query()->where('name', $domainName)->first();
        if (! $domain) {
            return response()->json([
                'message' => 'Domain panelde bulunamadı.',
            ], 404);
        }

        $mailHost = 'mail.'.$domainName;
        $aDomain = @gethostbynamel($domainName);
        $domainDnsOk = is_array($aDomain) && count($aDomain) > 0 && $aDomain[0] !== $domainName;
        $ipGuess = ($domainDnsOk && is_array($aDomain) && count($aDomain) > 0) ? (string) $aDomain[0] : null;

        $rows = [
            ['type' => 'A', 'name' => 'mail', 'value' => $ipGuess, 'ttl' => 3600, 'priority' => null],
            ['type' => 'A', 'name' => 'webmail', 'value' => $ipGuess, 'ttl' => 3600, 'priority' => null],
            ['type' => 'MX', 'name' => '@', 'value' => $mailHost, 'ttl' => 3600, 'priority' => 10],
            ['type' => 'TXT', 'name' => '@', 'value' => 'v=spf1 mx a ~all', 'ttl' => 3600, 'priority' => null],
            ['type' => 'TXT', 'name' => '_dmarc', 'value' => 'v=DMARC1; p=none; rua=mailto:postmaster@'.$domainName, 'ttl' => 3600, 'priority' => null],
        ];

        $created = [];
        $skipped = [];
        $errors = [];
        foreach ($rows as $row) {
            $type = strtoupper((string) $row['type']);
            $name = (string) $row['name'];
            $value = trim((string) ($row['value'] ?? ''));
            $ttl = (int) ($row['ttl'] ?? 3600);
            $priority = $row['priority'];

            if (! in_array($type, ['A', 'MX', 'TXT'], true)) {
                $skipped[] = ['record' => $row, 'reason' => 'unsupported_type'];

                continue;
            }
            if ($value === '') {
                $skipped[] = ['record' => $row, 'reason' => 'missing_value'];

                continue;
            }

            $exists = $domain->dnsRecords()
                ->where('type', $type)
                ->where('name', $name)
                ->where('value', $value)
                ->exists();
            if ($exists) {
                $skipped[] = ['record' => $row, 'reason' => 'already_exists'];

                continue;
            }

            $record = $domain->dnsRecords()->create([
                'type' => $type,
                'name' => $name,
                'value' => $value,
                'ttl' => $ttl,
                'priority' => $priority,
            ]);
            $enginePayload = [
                'id' => (string) $record->id,
                'type' => $type,
                'name' => $name,
                'value' => $value,
                'ttl' => $ttl,
                'priority' => $priority,
            ];
            $engineRes = $this->engine->dnsCreate($domainName, $enginePayload);
            if (isset($engineRes['error']) && is_string($engineRes['error']) && $engineRes['error'] !== '') {
                $errors[] = ['record' => $row, 'error' => $engineRes['error']];
            }
            $created[] = $row;
        }

        return response()->json([
            'message' => 'DNS otomatik uygulama tamamlandı.',
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function setupMailStack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ]);
        $domainName = strtolower(trim((string) $validated['domain']));

        $domain = Domain::query()->where('name', $domainName)->first();
        if (! $domain) {
            return response()->json([
                'message' => 'Domain panelde bulunamadı.',
            ], 404);
        }

        $proc = new Process(['sudo', '-n', '/usr/local/sbin/hostvim-security', 'mail-stack-setup', $domainName]);
        $proc->setTimeout(300);
        $proc->run();
        $output = trim($proc->getOutput()."\n".$proc->getErrorOutput());
        if (! $proc->isSuccessful()) {
            return response()->json([
                'message' => 'Mail stack kurulumu başarısız',
                'output' => $output,
            ], 422);
        }

        // Kurulumdan sonra DNS kayıtlarını otomatik dene.
        $dnsReq = new Request(['domain' => $domainName]);
        $dnsResp = $this->wizardApplyDns($dnsReq);
        $dnsBody = method_exists($dnsResp, 'getData') ? $dnsResp->getData(true) : null;
        $checkReq = new Request(['domain' => $domainName]);
        $checkResp = $this->wizardChecks($checkReq);
        $checkBody = method_exists($checkResp, 'getData') ? $checkResp->getData(true) : null;

        $remediations = [];
        foreach ((array) ($checkBody['checks'] ?? []) as $row) {
            $ok = (bool) ($row['ok'] ?? false);
            if ($ok) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            $remediations[] = match ($key) {
                'mail_dns' => 'DNS panelinden mail alt alanı için A kaydı ekleyin (mail -> sunucu IP).',
                'webmail_dns' => 'DNS panelinden webmail alt alanı için A kaydı ekleyin (webmail -> sunucu IP).',
                'smtp_port' => 'Firewall/Security Group üzerinde 587 ve 465 portlarını açın; postfix dinliyor mu kontrol edin.',
                'imap_port' => 'Firewall/Security Group üzerinde 993 ve 143 portlarını açın; dovecot dinliyor mu kontrol edin.',
                'webmail_https' => 'Nginx/Apache webmail hostunu ve 443 TLS ayarını doğrulayın.',
                default => 'Alan adı DNS ve servis yapılandırmasını tekrar kontrol edin.',
            };
        }
        $remediations = array_values(array_unique($remediations));

        return response()->json([
            'message' => 'Mail stack kurulumu tamamlandı',
            'output' => $output,
            'dns' => $dnsBody,
            'validation' => $checkBody,
            'remediations' => $remediations,
        ]);
    }

    private function probePort(string $host, int $port, float $timeout): array
    {
        $errno = 0;
        $errstr = '';
        $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (is_resource($sock)) {
            fclose($sock);

            return ['ok' => true, 'error' => null];
        }

        return ['ok' => false, 'error' => (trim($errstr) !== '' ? $errstr : 'errno: '.$errno)];
    }
}
