<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EngineApiService
{
    private string $baseUrl;

    private string $internalKey;

    private string $jwtSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('panelsar.engine_url', 'http://127.0.0.1:9090'), '/');
        $this->internalKey = (string) config('panelsar.engine_internal_key', '');
        $this->jwtSecret = (string) config('panelsar.engine_secret', '');
    }

    private function client(): PendingRequest
    {
        $req = Http::timeout(45)->acceptJson();

        if ($this->internalKey !== '') {
            return $req->withHeaders(['X-Panelsar-Engine-Key' => $this->internalKey]);
        }

        if ($this->jwtSecret !== '') {
            return $req->withToken($this->generateLegacyToken());
        }

        return $req;
    }

    private function clientLong(int $timeout = 600): PendingRequest
    {
        $req = Http::timeout($timeout)->acceptJson();

        if ($this->internalKey !== '') {
            return $req->withHeaders(['X-Panelsar-Engine-Key' => $this->internalKey]);
        }

        if ($this->jwtSecret !== '') {
            return $req->withToken($this->generateLegacyToken());
        }

        return $req;
    }

    public function getSystemStats(): array
    {
        return $this->get('/api/v1/system/stats')['data'] ?? [];
    }

    public function getServices(): array
    {
        return $this->get('/api/v1/services')['services'] ?? [];
    }

    public function controlService(string $name, string $action): array
    {
        return $this->post("/api/v1/services/{$name}/{$action}");
    }

    public function createSite(string $domain, int $userId, string $phpVersion = '8.2', string $serverType = 'nginx'): array
    {
        $serverType = in_array($serverType, ['nginx', 'apache'], true) ? $serverType : 'nginx';

        return $this->postChecked('/api/v1/sites', [
            'domain' => $domain,
            'user_id' => $userId,
            'php_version' => $phpVersion,
            'server_type' => $serverType,
        ]);
    }

    public function deleteSite(string $domain): array
    {
        $path = '/api/v1/sites/'.rawurlencode($domain);

        return $this->deleteChecked($path);
    }

    public function suspendSite(string $domain): array
    {
        return $this->postChecked('/api/v1/sites/'.rawurlencode($domain).'/suspend', []);
    }

    public function activateSite(string $domain): array
    {
        return $this->postChecked('/api/v1/sites/'.rawurlencode($domain).'/activate', []);
    }

    public function issueSSL(string $domain, ?string $email = null): array
    {
        $data = ['domain' => $domain];
        if ($email !== null && $email !== '') {
            $data['email'] = $email;
        }

        return $this->postChecked('/api/v1/ssl/issue', $data);
    }

    public function renewSSL(string $domain): array
    {
        return $this->postChecked('/api/v1/ssl/renew', ['domain' => $domain]);
    }

    public function revokeSSL(string $domain): array
    {
        return $this->postChecked('/api/v1/ssl/revoke', ['domain' => $domain]);
    }

    public function reloadNginx(): array
    {
        return $this->post('/api/v1/nginx/reload');
    }

    public function listFiles(string $domain, string $path = ''): array
    {
        $q = http_build_query(['domain' => $domain, 'path' => $path]);

        return $this->get('/api/v1/files?'.$q)['entries'] ?? [];
    }

    /**
     * @return list<array{path: string, line: int, preview: string}>
     */
    public function searchFiles(string $domain, string $path, string $query): array
    {
        $q = http_build_query([
            'domain' => $domain,
            'path' => $path,
            'q' => $query,
        ]);
        $json = $this->get('/api/v1/files/search?'.$q);

        return $json['hits'] ?? [];
    }

    public function mkdirFile(string $domain, string $path): array
    {
        return $this->post('/api/v1/files/mkdir', ['domain' => $domain, 'path' => $path]);
    }

    public function deleteFile(string $domain, string $path): array
    {
        $q = http_build_query(['domain' => $domain, 'path' => $path]);

        return $this->delete('/api/v1/files?'.$q);
    }

    public function readFile(string $domain, string $path): string
    {
        $q = http_build_query(['domain' => $domain, 'path' => $path]);
        $json = $this->get('/api/v1/files/read?'.$q);

        return (string) ($json['content'] ?? '');
    }

    public function writeFile(string $domain, string $path, string $content): array
    {
        return $this->post('/api/v1/files/write', [
            'domain' => $domain,
            'path' => $path,
            'content' => $content,
        ]);
    }

    public function uploadFile(string $domain, string $path, UploadedFile $file): array
    {
        try {
            $req = Http::timeout(120)->acceptJson();
            if ($this->internalKey !== '') {
                $req = $req->withHeaders(['X-Panelsar-Engine-Key' => $this->internalKey]);
            } elseif ($this->jwtSecret !== '') {
                $req = $req->withToken($this->generateLegacyToken());
            }
            $response = $req->attach(
                'file',
                file_get_contents($file->getRealPath()) ?: '',
                $file->getClientOriginalName()
            )->post($this->baseUrl.'/api/v1/files/upload', [
                'domain' => $domain,
                'path' => $path,
            ]);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                return ['error' => is_string($json['error'] ?? null) ? $json['error'] : ($response->body() ?: 'HTTP '.$response->status())];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error('Engine API file upload failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function listBackups(): array
    {
        return $this->get('/api/v1/backups')['backups'] ?? [];
    }

    public function queueBackup(string $domain, string $type = 'full', ?int $panelBackupId = null): array
    {
        $payload = ['domain' => $domain, 'type' => $type];
        if ($panelBackupId !== null) {
            $payload['panel_backup_id'] = $panelBackupId;
        }

        return $this->post('/api/v1/backups', $payload);
    }

    public function restoreBackup(string $id): array
    {
        return $this->post('/api/v1/backups/'.rawurlencode($id).'/restore');
    }

    public function dnsList(string $domain): array
    {
        return $this->get('/api/v1/dns/'.rawurlencode($domain))['records'] ?? [];
    }

    public function dnsCreate(string $domain, array $data): array
    {
        return $this->post('/api/v1/dns/'.rawurlencode($domain), $data);
    }

    public function dnsDeleteRecord(string $domain, string $id): array
    {
        return $this->delete('/api/v1/dns/'.rawurlencode($domain).'/'.rawurlencode($id));
    }

    public function ftpList(string $domain): array
    {
        return $this->get('/api/v1/ftp/'.rawurlencode($domain))['accounts'] ?? [];
    }

    public function ftpProvision(string $domain, array $data): array
    {
        return $this->post('/api/v1/ftp/'.rawurlencode($domain), $data);
    }

    public function ftpDeleteAccount(string $domain, string $username): array
    {
        $path = '/api/v1/ftp/'.rawurlencode($domain).'/'.rawurlencode($username);

        return $this->delete($path);
    }

    public function mailOverview(string $domain): array
    {
        return $this->get('/api/v1/mail/'.rawurlencode($domain));
    }

    public function mailCreateMailbox(string $domain, array $data): array
    {
        return $this->post('/api/v1/mail/'.rawurlencode($domain).'/mailbox', $data);
    }

    public function mailDeleteMailbox(string $domain, string $email): array
    {
        $q = http_build_query(['email' => $email]);

        return $this->delete('/api/v1/mail/'.rawurlencode($domain).'/mailbox?'.$q);
    }

    public function securityOverview(): array
    {
        return $this->get('/api/v1/security/overview');
    }

    public function applyFirewallRule(array $payload): array
    {
        return $this->post('/api/v1/security/firewall/rule', $payload);
    }

    public function installerApps(): array
    {
        return $this->get('/api/v1/installer/apps')['apps'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $dbPayload
     */
    public function installerRun(string $app, string $domain, array $dbPayload = []): array
    {
        return $this->postLongChecked('/api/v1/installer/install', array_merge([
            'app' => $app,
            'domain' => $domain,
        ], $dbPayload));
    }

    public function runSiteTool(string $domain, string $tool, string $action): array
    {
        $path = '/api/v1/sites/'.rawurlencode($domain).'/tools';

        return $this->postLongChecked($path, [
            'tool' => $tool,
            'action' => $action,
        ]);
    }

    public function validateLicense(string $key): array
    {
        return $this->post('/api/v1/license/validate', ['key' => $key]);
    }

    public function engineCronList(): array
    {
        return $this->get('/api/v1/cron')['jobs'] ?? [];
    }

    public function engineCronCreate(array $payload): array
    {
        return $this->post('/api/v1/cron', $payload);
    }

    public function engineCronDelete(string $id): array
    {
        return $this->delete('/api/v1/cron/'.$id);
    }

    public function engineCronUpdate(string $id, array $payload): array
    {
        return $this->patchJson('/api/v1/cron/'.rawurlencode($id), $payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStackModules(): array
    {
        return $this->get('/api/v1/stack/modules')['modules'] ?? [];
    }

    public function installStackBundle(string $bundleId): array
    {
        return $this->postLongChecked('/api/v1/stack/install', [
            'bundle_id' => $bundleId,
        ], 900);
    }

    private function postChecked(string $path, array $data = []): array
    {
        return $this->postLongChecked($path, $data, 45);
    }

    private function postLongChecked(string $path, array $data = [], int $timeout = 600): array
    {
        try {
            $response = $this->clientLong($timeout)->post($this->baseUrl.$path, $data);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $msg = is_string($json['error'] ?? null) ? $json['error'] : ($response->body() ?: 'HTTP '.$response->status());

                return ['error' => $msg, 'output' => is_string($json['output'] ?? null) ? $json['output'] : null];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error("Engine API POST {$path} failed: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    private function deleteChecked(string $path): array
    {
        try {
            $response = $this->client()->delete($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $msg = is_string($json['error'] ?? null) ? $json['error'] : ($response->body() ?: 'HTTP '.$response->status());

                return ['error' => $msg];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error("Engine API DELETE {$path} failed: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    private function get(string $path): array
    {
        try {
            $response = $this->client()->get($this->baseUrl.$path);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error("Engine API GET {$path} failed: {$e->getMessage()}");

            return [];
        }
    }

    private function post(string $path, array $data = []): array
    {
        try {
            $response = $this->client()->post($this->baseUrl.$path, $data);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error("Engine API POST {$path} failed: {$e->getMessage()}");

            return [];
        }
    }

    private function delete(string $path): array
    {
        try {
            $response = $this->client()->delete($this->baseUrl.$path);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            Log::error("Engine API DELETE {$path} failed: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function patchJson(string $path, array $data): array
    {
        try {
            $response = $this->client()->patch($this->baseUrl.$path, $data);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $msg = is_string($json['error'] ?? null) ? $json['error'] : ($response->body() ?: 'HTTP '.$response->status());

                return ['error' => $msg];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error("Engine API PATCH {$path} failed: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    private function generateLegacyToken(): string
    {
        $payload = base64_encode(json_encode([
            'iss' => 'panelsar-panel',
            'iat' => time(),
            'exp' => time() + 300,
        ]));

        return $payload.'.'.hash_hmac('sha256', $payload, $this->jwtSecret);
    }

    public static function isLikelyConnectionFailure(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        $m = strtolower($message);

        return str_contains($m, 'curl error')
            || str_contains($m, 'connection refused')
            || str_contains($m, 'could not connect')
            || str_contains($m, 'failed to connect')
            || str_contains($m, 'operation timed out')
            || str_contains($m, 'timed out')
            || str_contains($m, 'name or service not known')
            || str_contains($m, 'could not resolve host');
    }
}
