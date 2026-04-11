<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
        $this->baseUrl = rtrim(config('hostvim.engine_url', 'http://127.0.0.1:9090'), '/');
        $this->internalKey = (string) config('hostvim.engine_internal_key', '');
        $this->jwtSecret = (string) config('hostvim.engine_secret', '');
    }

    private function engineAuthConfigured(): bool
    {
        return $this->internalKey !== '' || $this->jwtSecret !== '';
    }

    /**
     * @return array{error: string}
     */
    private function missingEngineCredentialsPayload(): array
    {
        return ['error' => (string) __('messages.engine_auth_missing')];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function formatEngineHttpError(Response $response, array $json): string
    {
        $msg = is_string($json['error'] ?? null)
            ? (string) $json['error']
            : (string) ($response->body() ?: 'HTTP '.$response->status());
        $status = $response->status();
        if (($status === 401 || $status === 403) && (
            str_contains($msg, 'Authorization header required')
            || str_contains($msg, 'Invalid token')
            || str_contains($msg, 'Invalid authorization format')
        )) {
            return (string) __('messages.engine_auth_mismatch');
        }

        return $msg;
    }

    private function withEngineAuth(PendingRequest $req): PendingRequest
    {
        if ($this->internalKey !== '') {
            return $req->withHeaders([
                'X-Hostvim-Engine-Key' => $this->internalKey,
                'X-Panelsar-Engine-Key' => $this->internalKey,
            ]);
        }

        if ($this->jwtSecret !== '') {
            return $req->withToken($this->generateLegacyToken());
        }

        return $req;
    }

    private function client(): PendingRequest
    {
        return $this->withEngineAuth(Http::timeout(45)->acceptJson());
    }

    private function clientLong(int $timeout = 600): PendingRequest
    {
        return $this->withEngineAuth(Http::timeout($timeout)->acceptJson());
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

    /**
     * Ana site dizini + vhost + engine-state (dns/mail/ftp) yeniden adlandırma.
     *
     * @return array<string, mixed>
     */
    public function renameSite(string $from, string $to): array
    {
        return $this->postLongChecked('/api/v1/sites/rename', [
            'from' => $from,
            'to' => $to,
        ], 180);
    }

    public function suspendSite(string $domain): array
    {
        return $this->postChecked('/api/v1/sites/'.rawurlencode($domain).'/suspend', []);
    }

    public function activateSite(string $domain): array
    {
        return $this->postChecked('/api/v1/sites/'.rawurlencode($domain).'/activate', []);
    }

    /**
     * @param  array{hostname: string, path_segment: string, php_version?: string}  $payload
     * @return array<string, mixed>
     */
    public function siteAddSubdomain(string $parentDomain, array $payload): array
    {
        return $this->postChecked('/api/v1/sites/'.rawurlencode($parentDomain).'/subdomains', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function siteRemoveSubdomain(string $parentDomain, string $pathSegment): array
    {
        return $this->deleteJsonChecked('/api/v1/sites/'.rawurlencode($parentDomain).'/subdomains', [
            'path_segment' => $pathSegment,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function siteAddAlias(string $parentDomain, string $hostname): array
    {
        return $this->postChecked('/api/v1/sites/'.rawurlencode($parentDomain).'/aliases', [
            'hostname' => $hostname,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function siteRemoveAlias(string $parentDomain, string $hostname): array
    {
        return $this->deleteJsonChecked('/api/v1/sites/'.rawurlencode($parentDomain).'/aliases', [
            'hostname' => $hostname,
        ]);
    }

    public function issueSSL(string $domain, ?string $email = null): array
    {
        $data = ['domain' => $domain];
        if ($email !== null && $email !== '') {
            $data['email'] = $email;
        }

        // certbot + ACME doğrulaması 45 sn’yi aşabilir; kısa timeout yanlış 502/timeout üretir.
        return $this->postLongChecked('/api/v1/ssl/issue', $data, 900);
    }

    public function renewSSL(string $domain): array
    {
        return $this->postLongChecked('/api/v1/ssl/renew', ['domain' => $domain], 900);
    }

    public function revokeSSL(string $domain): array
    {
        return $this->postLongChecked('/api/v1/ssl/revoke', ['domain' => $domain], 120);
    }

    public function uploadManualSSL(string $domain, string $certificate, string $privateKey): array
    {
        return $this->postLongChecked('/api/v1/ssl/manual', [
            'domain' => $domain,
            'certificate' => $certificate,
            'private_key' => $privateKey,
        ], 120);
    }

    public function reloadNginx(): array
    {
        return $this->post('/api/v1/nginx/reload');
    }

    /**
     * @return array{domain?: string, performance_mode?: string, server_type?: string, supported_servers?: list<string>, error?: string}
     */
    public function getSitePerformance(string $domain): array
    {
        return $this->get('/api/v1/sites/'.rawurlencode($domain).'/performance');
    }

    /**
     * @return array{domain?: string, performance_mode?: string, ok?: bool, error?: string}
     */
    public function setSitePerformance(string $domain, string $mode): array
    {
        return $this->postChecked('/api/v1/sites/'.rawurlencode($domain).'/performance', [
            'mode' => $mode,
        ]);
    }

    /**
     * @return array{domain?: string, path?: string, content?: string, error?: string, hint?: string}
     */
    public function getSiteNginxVhost(string $domain): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $path = '/api/v1/sites/'.rawurlencode($domain).'/nginx-vhost';
            $response = $this->client()->get($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $payload = ['error' => $this->formatEngineHttpError($response, $json)];
                if (isset($json['path'])) {
                    $payload['path'] = $json['path'];
                }
                if (isset($json['hint'])) {
                    $payload['hint'] = $json['hint'];
                }
                if (array_key_exists('can_revert', $json)) {
                    $payload['can_revert'] = (bool) $json['can_revert'];
                }

                return $payload;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::error('Engine API GET nginx-vhost failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array{domain?: string, path?: string, message?: string, ok?: bool, can_revert?: bool, error?: string}
     */
    public function revertSiteNginxVhost(string $domain): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $path = '/api/v1/sites/'.rawurlencode($domain).'/nginx-vhost/revert';
            $response = $this->client()
                ->withBody('{}', 'application/json')
                ->post($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $payload = ['error' => $this->formatEngineHttpError($response, $json)];
                if (isset($json['path'])) {
                    $payload['path'] = $json['path'];
                }

                return $payload;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::error('Engine API POST nginx-vhost/revert failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array{domain?: string, path?: string, message?: string, ok?: bool, error?: string}
     */
    public function updateSiteNginxVhost(string $domain, string $content): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $path = '/api/v1/sites/'.rawurlencode($domain).'/nginx-vhost';
            $response = $this->client()
                ->withBody(json_encode(['content' => $content], JSON_THROW_ON_ERROR), 'application/json')
                ->put($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $payload = ['error' => $this->formatEngineHttpError($response, $json)];
                if (isset($json['path'])) {
                    $payload['path'] = $json['path'];
                }

                return $payload;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::error('Engine API PUT nginx-vhost failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array{domain?: string, path?: string, content?: string, can_revert?: bool, error?: string, hint?: string}
     */
    public function getSiteApacheVhost(string $domain): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $path = '/api/v1/sites/'.rawurlencode($domain).'/apache-vhost';
            $response = $this->client()->get($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $payload = ['error' => $this->formatEngineHttpError($response, $json)];
                if (isset($json['path'])) {
                    $payload['path'] = $json['path'];
                }
                if (isset($json['hint'])) {
                    $payload['hint'] = $json['hint'];
                }
                if (array_key_exists('can_revert', $json)) {
                    $payload['can_revert'] = (bool) $json['can_revert'];
                }

                return $payload;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::error('Engine API GET apache-vhost failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array{domain?: string, path?: string, message?: string, ok?: bool, can_revert?: bool, error?: string}
     */
    public function updateSiteApacheVhost(string $domain, string $content): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $path = '/api/v1/sites/'.rawurlencode($domain).'/apache-vhost';
            $response = $this->client()
                ->withBody(json_encode(['content' => $content], JSON_THROW_ON_ERROR), 'application/json')
                ->put($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $payload = ['error' => $this->formatEngineHttpError($response, $json)];
                if (isset($json['path'])) {
                    $payload['path'] = $json['path'];
                }

                return $payload;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::error('Engine API PUT apache-vhost failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array{domain?: string, path?: string, message?: string, ok?: bool, can_revert?: bool, error?: string}
     */
    public function revertSiteApacheVhost(string $domain): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $path = '/api/v1/sites/'.rawurlencode($domain).'/apache-vhost/revert';
            $response = $this->client()
                ->withBody('{}', 'application/json')
                ->post($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $payload = ['error' => $this->formatEngineHttpError($response, $json)];
                if (isset($json['path'])) {
                    $payload['path'] = $json['path'];
                }

                return $payload;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::error('Engine API POST apache-vhost/revert failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function setSiteDocumentRoot(string $domain, ?string $variant = null, ?string $profile = null, ?string $customPath = null): array
    {
        $payload = [];
        $v = is_string($variant) ? strtolower(trim($variant)) : '';
        if (in_array($v, ['root', 'public'], true)) {
            $payload['variant'] = $v;
        }
        $p = is_string($profile) ? strtolower(trim($profile)) : '';
        if ($p !== '') {
            $payload['profile'] = $p;
        }
        $cp = is_string($customPath) ? trim($customPath) : '';
        if ($cp !== '') {
            $payload['custom_path'] = $cp;
        }
        if ($payload === []) {
            $payload['variant'] = 'root';
        }

        return $this->postChecked('/api/v1/sites/'.rawurlencode($domain).'/document-root', $payload);
    }

    /**
     * @return array{
     *  nginx_manage_vhosts: bool,
     *  nginx_reload_after_vhost: bool,
     *  apache_manage_vhosts: bool,
     *  apache_reload_after_vhost: bool,
     *  php_fpm_manage_pools: bool,
     *  php_fpm_reload_after_pool: bool,
     *  php_fpm_socket: string,
     *  php_fpm_listen_dir: string,
     *  php_fpm_pool_dir_template: string,
     *  php_fpm_pool_user: string,
     *  php_fpm_pool_group: string
     * }
     */
    public function getWebServerSettings(): array
    {
        return $this->get('/api/v1/webserver/settings')['settings'] ?? [];
    }

    /**
     * @return array{nginx?: array{installed?: bool,active?: bool}, apache?: array{installed?: bool,active?: bool}}
     */
    public function getWebServerServices(): array
    {
        return $this->get('/api/v1/webserver/services')['services'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message?: string, settings?: array<string, mixed>, reload?: array<string, mixed>}
     */
    public function updateWebServerSettings(array $payload): array
    {
        return $this->patchJson('/api/v1/webserver/settings', $payload);
    }

    /**
     * @return array{modules?: list<array{name:string,enabled:bool}>, error?: string}
     */
    public function getApacheModules(): array
    {
        return $this->get('/api/v1/webserver/apache/modules');
    }

    /**
     * @return array{module?: string, enabled?: bool, error?: string}
     */
    public function setApacheModule(string $name, bool $enabled): array
    {
        return $this->postChecked('/api/v1/webserver/apache/modules/'.rawurlencode($name).'/toggle', [
            'enabled' => $enabled,
        ]);
    }

    /**
     * @return array{scope?: string, content?: string, error?: string}
     */
    public function getNginxConfig(string $scope = 'main'): array
    {
        return $this->get('/api/v1/webserver/nginx/config?scope='.rawurlencode($scope));
    }

    /**
     * @return array{message?: string, scope?: string, error?: string}
     */
    public function updateNginxConfig(string $scope, string $content, bool $testReload = true): array
    {
        return $this->postChecked('/api/v1/webserver/nginx/config', [
            'scope' => $scope,
            'content' => $content,
            'test_reload' => $testReload,
        ]);
    }

    public function rebootSystem(): array
    {
        return $this->post('/api/v1/system/reboot');
    }

    public function getProcesses(): array
    {
        return $this->get('/api/v1/system/processes')['processes'] ?? [];
    }

    public function killProcess(int $pid): array
    {
        return $this->post('/api/v1/system/processes/kill', ['pid' => $pid]);
    }

    public function getPhpVersions(): array
    {
        return $this->get('/api/v1/php/versions')['versions'] ?? [];
    }

    public function getPhpIni(string $version): array
    {
        return $this->get('/api/v1/php/'.rawurlencode($version).'/ini');
    }

    /**
     * @param  array{ini: string, reload?: bool}  $payload
     * @return array<string, mixed>
     */
    public function updatePhpIni(string $version, array $payload): array
    {
        return $this->patchJson('/api/v1/php/'.rawurlencode($version).'/ini', $payload);
    }

    public function getPhpModules(string $version): array
    {
        return $this->get('/api/v1/php/'.rawurlencode($version).'/modules');
    }

    /**
     * @param  array{modules: array<int, array{directive: string, name: string, enabled: bool}>, reload?: bool}  $payload
     * @return array<string, mixed>
     */
    public function updatePhpModules(string $version, array $payload): array
    {
        return $this->patchJson('/api/v1/php/'.rawurlencode($version).'/modules', $payload);
    }

    /**
     * @return array{entries: list<array<string, mixed>>, total: int, offset: int, limit: int, error: ?string}
     */
    /**
     * @return array{entries: list<array<string, mixed>>, total: int, offset: int, limit: int, error: ?string}
     */
    public function listFilesResult(
        string $domain,
        string $path = '',
        int $limit = 200,
        int $offset = 0,
        string $sort = 'name',
        string $order = 'asc',
    ): array {
        try {
            $response = $this->client()->get($this->baseUrl.'/api/v1/files', [
                'domain' => $domain,
                'path' => $path,
                'limit' => $limit,
                'offset' => $offset,
                'sort' => $sort,
                'order' => $order,
            ]);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $msg = is_string($json['error'] ?? null) ? $json['error'] : ($response->body() ?: 'HTTP '.$response->status());

                return ['entries' => [], 'total' => 0, 'offset' => 0, 'limit' => 0, 'error' => $msg];
            }

            return [
                'entries' => $json['entries'] ?? [],
                'total' => (int) ($json['total'] ?? 0),
                'offset' => (int) ($json['offset'] ?? 0),
                'limit' => (int) ($json['limit'] ?? 0),
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Engine API GET /files failed: '.$e->getMessage());

            return ['entries' => [], 'total' => 0, 'offset' => 0, 'limit' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{message?: string, error?: string}
     */
    public function renameFile(string $domain, string $from, string $to): array
    {
        return $this->postEngineJsonChecked('/api/v1/files/rename', [
            'domain' => $domain,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * @return array{message?: string, error?: string}
     */
    public function moveFile(string $domain, string $from, string $to): array
    {
        return $this->postEngineJsonChecked('/api/v1/files/move', [
            'domain' => $domain,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function copyFile(string $domain, string $from, string $to): array
    {
        return $this->postEngineJsonChecked('/api/v1/files/copy', [
            'domain' => $domain,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function chmodFile(string $domain, string $path, string $mode): array
    {
        return $this->postEngineJsonChecked('/api/v1/files/chmod', [
            'domain' => $domain,
            'path' => $path,
            'mode' => $mode,
        ]);
    }

    public function zipPath(string $domain, string $source, string $target): array
    {
        return $this->postLongChecked('/api/v1/files/zip', [
            'domain' => $domain,
            'source' => $source,
            'target' => $target,
        ], 1800);
    }

    public function unzipPath(string $domain, string $archive, string $targetDir, string $ifExists = 'fail'): array
    {
        return $this->postLongChecked('/api/v1/files/unzip', [
            'domain' => $domain,
            'archive' => $archive,
            'target_dir' => $targetDir,
            'if_exists' => $ifExists,
        ], 1800);
    }

    /**
     * @return array{content_base64?: string, filename?: string, mime?: string, size?: int, error?: string}
     */
    public function downloadFile(string $domain, string $path): array
    {
        $q = http_build_query(['domain' => $domain, 'path' => $path]);
        try {
            $response = $this->client()->get($this->baseUrl.'/api/v1/files/download?'.$q);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $msg = is_string($json['error'] ?? null) ? $json['error'] : ($response->body() ?: 'HTTP '.$response->status());

                return ['error' => $msg];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error('Engine API GET /files/download failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
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
        return $this->postEngineJsonChecked('/api/v1/files/mkdir', ['domain' => $domain, 'path' => $path]);
    }

    public function deleteFile(string $domain, string $path): array
    {
        $q = http_build_query(['domain' => $domain, 'path' => $path]);

        try {
            $response = $this->client()->delete($this->baseUrl.'/api/v1/files?'.$q);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                $msg = is_string($json['error'] ?? null) ? $json['error'] : ($response->body() ?: 'HTTP '.$response->status());

                return ['error' => $msg];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error('Engine API DELETE /files failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    public function readFile(string $domain, string $path): string
    {
        $q = http_build_query(['domain' => $domain, 'path' => $path]);
        $json = $this->get('/api/v1/files/read?'.$q);

        return (string) ($json['content'] ?? '');
    }

    public function writeFile(string $domain, string $path, string $content): array
    {
        return $this->postEngineJsonChecked('/api/v1/files/write', [
            'domain' => $domain,
            'path' => $path,
            'content' => $content,
        ]);
    }

    public function createFile(string $domain, string $path, string $content): array
    {
        return $this->postEngineJsonChecked('/api/v1/files/create', [
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
                $req = $req->withHeaders(['X-Hostvim-Engine-Key' => $this->internalKey]);
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

    /**
     * Multipart: archive (tar.gz). Uzak yedek indirildikten sonra engine üzerinde extract edilir.
     *
     * @return array<string, mixed>
     */
    /**
     * @return array{bytes?: int, exists?: bool, error?: string}
     */
    public function getSiteDiskUsage(string $domain): array
    {
        if (! $this->engineAuthConfigured()) {
            return ['error' => (string) __('messages.engine_auth_missing'), 'bytes' => 0, 'exists' => false];
        }
        try {
            $response = $this->client()->get($this->baseUrl.'/api/v1/sites/'.rawurlencode($domain).'/disk-usage');
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                return [
                    'error' => $this->formatEngineHttpError($response, $json),
                    'bytes' => 0,
                    'exists' => false,
                ];
            }

            return [
                'bytes' => (int) ($json['bytes'] ?? 0),
                'exists' => (bool) ($json['exists'] ?? true),
            ];
        } catch (\Exception $e) {
            Log::error('Engine API GET /sites/disk-usage failed: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'bytes' => 0, 'exists' => false];
        }
    }

    public function restoreBackupUpload(string $localPath, ?string $filename = null): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }
        if (! is_readable($localPath)) {
            return ['error' => 'Archive file is not readable'];
        }
        $timeout = (int) config('hostvim.engine_restore_upload_timeout', 7200);
        if ($timeout < 120) {
            $timeout = 7200;
        }
        $uploadName = $filename !== null && $filename !== '' ? $filename : basename($localPath);
        $stream = null;
        try {
            $req = Http::timeout($timeout)->acceptJson();
            if ($this->internalKey !== '') {
                $req = $req->withHeaders([
                    'X-Hostvim-Engine-Key' => $this->internalKey,
                    'X-Panelsar-Engine-Key' => $this->internalKey,
                ]);
            } elseif ($this->jwtSecret !== '') {
                $req = $req->withToken($this->generateLegacyToken());
            }
            $stream = fopen($localPath, 'rb');
            if ($stream === false) {
                return ['error' => 'Could not open archive'];
            }
            $response = $req->attach('archive', $stream, $uploadName)
                ->post($this->baseUrl.'/api/v1/backups/restore-upload');
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                return ['error' => $this->formatEngineHttpError($response, $json)];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error('Engine API backup restore-upload failed: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        } finally {
            if (is_resource($stream ?? null)) {
                fclose($stream);
            }
        }
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

        return $this->deleteChecked($path);
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

    public function mailDeleteDomainState(string $domain): array
    {
        return $this->deleteChecked('/api/v1/mail/'.rawurlencode($domain));
    }

    /**
     * @param  array{source: string, destination: string}  $data
     */
    public function mailAddForwarder(string $domain, array $data): array
    {
        return $this->post('/api/v1/mail/'.rawurlencode($domain).'/forwarder', $data);
    }

    public function mailDeleteForwarder(string $domain, string $source, string $destination): array
    {
        $q = http_build_query(['source' => $source, 'destination' => $destination]);

        return $this->delete('/api/v1/mail/'.rawurlencode($domain).'/forwarder?'.$q);
    }

    /**
     * @param  array{email: string, password?: string, quota_mb?: int}  $data
     * @return array<string, mixed>
     */
    public function mailPatchMailbox(string $domain, array $data): array
    {
        return $this->patchJson('/api/v1/mail/'.rawurlencode($domain).'/mailbox', $data);
    }

    public function securityOverview(): array
    {
        return $this->get('/api/v1/security/overview');
    }

    public function applyFirewallRule(array $payload): array
    {
        return $this->post('/api/v1/security/firewall/rule', $payload);
    }

    public function toggleFail2ban(bool $enabled): array
    {
        return $this->post('/api/v1/security/fail2ban/toggle', ['enabled' => $enabled]);
    }

    public function installFail2ban(): array
    {
        return $this->postChecked('/api/v1/security/fail2ban/install');
    }

    public function updateFail2banJail(int $bantime, int $findtime, int $maxretry): array
    {
        return $this->post('/api/v1/security/fail2ban/jail', [
            'bantime' => $bantime,
            'findtime' => $findtime,
            'maxretry' => $maxretry,
        ]);
    }

    public function toggleModSecurity(bool $enabled): array
    {
        return $this->post('/api/v1/security/modsecurity/toggle', ['enabled' => $enabled]);
    }

    public function installModSecurity(): array
    {
        return $this->postChecked('/api/v1/security/modsecurity/install');
    }

    public function toggleClamav(bool $enabled): array
    {
        return $this->post('/api/v1/security/clamav/toggle', ['enabled' => $enabled]);
    }

    public function runClamavScan(?string $target = null, ?string $domain = null): array
    {
        $payload = [];
        $domain = is_string($domain) ? trim($domain) : '';
        if ($domain !== '') {
            $payload['domain'] = $domain;
        } else {
            $target = is_string($target) ? trim($target) : '';
            if ($target !== '') {
                $payload['target'] = $target;
            }
        }

        return $this->postLongChecked('/api/v1/security/clamav/scan', $payload, 1800);
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    public function quarantineClamavFiles(array $paths): array
    {
        return $this->postLongChecked('/api/v1/security/clamav/quarantine', ['paths' => array_values($paths)], 180);
    }

    public function runMaldetScan(?string $target = null, ?string $domain = null): array
    {
        $payload = [];
        $domain = is_string($domain) ? trim($domain) : '';
        if ($domain !== '') {
            $payload['domain'] = $domain;
        } else {
            $target = is_string($target) ? trim($target) : '';
            if ($target !== '') {
                $payload['target'] = $target;
            }
        }

        return $this->postLongChecked('/api/v1/security/clamav/maldet-scan', $payload, 1800);
    }

    public function reconcileMailState(bool $dryRun = true, ?string $confirm = null): array
    {
        $payload = ['dry_run' => $dryRun];
        if ($confirm !== null && trim($confirm) !== '') {
            $payload['confirm'] = trim($confirm);
        }

        return $this->postLongChecked('/api/v1/security/mail/reconcile', $payload, 120);
    }

    public function getFimStatus(): array
    {
        return $this->get('/api/v1/security/fim/status');
    }

    public function createFimBaseline(): array
    {
        return $this->postChecked('/api/v1/security/fim/baseline');
    }

    public function runFimScan(): array
    {
        return $this->postLongChecked('/api/v1/security/fim/scan', [], 1800);
    }

    public function listSecurityAlerts(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        return $this->get('/api/v1/security/alerts?limit='.$limit);
    }

    public function getSecurityIntelPolicy(): array
    {
        return $this->get('/api/v1/security/intel/policy');
    }

    public function updateSecurityIntelPolicy(array $payload): array
    {
        return $this->postLongChecked('/api/v1/security/intel/policy', $payload, 120);
    }

    public function getSecurityIntelStatus(): array
    {
        return $this->get('/api/v1/security/intel/status');
    }

    /**
     * @return array{profile?: string, limits?: string, error?: string}
     */
    public function getNginxRateLimitProfile(): array
    {
        return $this->get('/api/v1/security/nginx/rate-limit/profile');
    }

    /**
     * @return array{message?: string, profile?: string, limits?: string, error?: string}
     */
    public function setNginxRateLimitProfile(string $profile): array
    {
        return $this->postChecked('/api/v1/security/nginx/rate-limit/profile', [
            'profile' => trim($profile),
        ]);
    }

    /**
     * @return array{rules?: list<array{id:string,domain:string,mode:string,target:string}>, error?: string}
     */
    public function getModSecuritySiteRules(): array
    {
        return $this->get('/api/v1/security/modsecurity/site-rules');
    }

    /**
     * @return array{message?: string, rule?: array{id:string,domain:string,mode:string,target:string}, error?: string}
     */
    public function addModSecuritySiteRule(string $domain, string $mode, ?string $target = null): array
    {
        return $this->postChecked('/api/v1/security/modsecurity/site-rule', [
            'operation' => 'add',
            'domain' => trim($domain),
            'mode' => trim($mode),
            'target' => trim((string) $target),
        ]);
    }

    /**
     * @return array{message?: string, error?: string}
     */
    public function removeModSecuritySiteRule(string $id): array
    {
        return $this->postChecked('/api/v1/security/modsecurity/site-rule', [
            'operation' => 'remove',
            'id' => trim($id),
        ]);
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
     * @return array{domain?: string, logs?: array<int, array<string, mixed>>, error?: string}
     */
    public function getSiteLogs(string $domain, int $lines = 200): array
    {
        $lines = max(20, min(1000, $lines));

        return $this->get('/api/v1/sites/'.rawurlencode($domain).'/logs?lines='.$lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSiteTraffic(string $domain, int $lines = 8000): array
    {
        $lines = max(100, min(20000, $lines));

        return $this->get('/api/v1/sites/'.rawurlencode($domain).'/traffic?lines='.$lines);
    }

    /**
     * Erişim günlüğü örneğinden tahmini çıkan bayt (UsageUpdate / bant genişliği göstergesi).
     */
    public function getSiteTrafficSampleBytesTotal(string $domain, int $lines = 8000): int
    {
        $res = $this->getSiteTraffic($domain, $lines);
        if (! empty($res['error'])) {
            return 0;
        }
        $traffic = $res['traffic'] ?? [];

        return (int) ($traffic['bytes_total'] ?? 0);
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
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        $attempts = 2;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = $this->clientLong($timeout)->post($this->baseUrl.$path, $data);
                $json = $response->json() ?? [];
                if (! $response->successful()) {
                    $msg = $this->formatEngineHttpError($response, $json);

                    $payload = ['error' => $msg];
                    if (is_string($json['output'] ?? null)) {
                        $payload['output'] = $json['output'];
                    }
                    foreach (['scan', 'infected_count', 'infected_files', 'infected_truncated', 'last_scan', 'scan_path', 'moved'] as $k) {
                        if (array_key_exists($k, $json)) {
                            $payload[$k] = $json[$k];
                        }
                    }

                    return $payload;
                }

                return $json;
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                Log::error("Engine API POST {$path} failed: {$msg}");

                $canRetry = $i < $attempts - 1 && self::isLikelyConnectionFailure($msg);
                if ($canRetry) {
                    // Geçici engine restart/bağlantı reset durumlarında bir kez daha deneyelim.
                    usleep(350000);

                    continue;
                }

                return ['error' => $msg];
            }
        }

        return ['error' => 'Engine API request failed'];
    }

    private function deleteChecked(string $path): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $response = $this->client()->delete($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                return ['error' => $this->formatEngineHttpError($response, $json)];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error("Engine API DELETE {$path} failed: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function deleteJsonChecked(string $path, array $data): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $response = $this->client()
                ->withBody(json_encode($data, JSON_THROW_ON_ERROR), 'application/json')
                ->delete($this->baseUrl.$path);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                return ['error' => $this->formatEngineHttpError($response, $json)];
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function postEngineJsonChecked(string $path, array $data): array
    {
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $response = $this->client()->post($this->baseUrl.$path, $data);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                return ['error' => $this->formatEngineHttpError($response, $json)];
            }

            return $json;
        } catch (\Exception $e) {
            Log::error("Engine API POST {$path} failed: {$e->getMessage()}");

            return ['error' => $e->getMessage()];
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
        if (! $this->engineAuthConfigured()) {
            return $this->missingEngineCredentialsPayload();
        }

        try {
            $response = $this->client()->patch($this->baseUrl.$path, $data);
            $json = $response->json() ?? [];
            if (! $response->successful()) {
                return ['error' => $this->formatEngineHttpError($response, $json)];
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
            'iss' => 'hostvim-panel',
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
            || str_contains($m, 'empty reply from server')
            || str_contains($m, 'connection refused')
            || str_contains($m, 'could not connect')
            || str_contains($m, 'failed to connect')
            || str_contains($m, 'operation timed out')
            || str_contains($m, 'timed out')
            || str_contains($m, 'name or service not known')
            || str_contains($m, 'could not resolve host');
    }
}
