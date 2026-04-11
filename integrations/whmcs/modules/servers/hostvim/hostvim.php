<?php
/**
 * Hostvim — WHMCS provisioning module (server / hosting product).
 *
 * Kurulum:
 * 1) Bu klasörü WHMCS sunucusuna kopyalayın: modules/servers/hostvim/
 * 2) Hostvim panel .env: HOSTVIM_WHMCS_SECRET=uzun-rastgele-gizli-deger
 *    İsteğe bağlı: HOSTVIM_SSO_PANEL_URL=https://panel.example.com/admin
 * 3) WHMCS → Yapılandırma → Sunucular → Yeni sunucu: Modül=hostvim
 *    - Hostname: panel kök URL (örn. https://panel.ornek.com veya IP)
 *    - Şifre: HOSTVIM_WHMCS_SECRET ile aynı (WHMCS şifre alanında saklanır)
 *    - Kullanıcı adı (yönetici SSO için): Hostvim admin hesabının e-postası
 * 4) Ürün → Modül ayarları: Hosting Package ID, PHP, web sunucusu, Let’s Encrypt
 * 5) Siparişte alan adı (WHMCS “Domain” alanı) dolu olmalı — engine’de site açılır
 *
 * Panel oturumu: WHMCS müşteri e-postası = panel giriş e-postası (clientsdetails.email).
 *
 * Ek API uçları (Bearer HOSTVIM_WHMCS_SECRET):
 *   GET  .../usage/accounts          — WHMCS UsageUpdate (tüm siteler)
 *   GET  .../usage/domain?email=&domain=
 *   POST .../email/create|delete
 *   POST .../ftp/create|delete
 *   POST .../database/create|delete
 *   GET  .../cron/list?email=
 *   POST .../cron/create|delete
 *   POST .../sso/mint | sso/mint-admin
 *   GET  .../dns/list?email=&domain=
 *   POST .../dns/create|import|import-zone|delete
 *   POST .../ssl/issue|renew
 *   POST .../backup/queue
 *   POST .../email/forwarder/create|delete
 *   POST .../database/rotate-password
 *   POST .../change-domain | service/renew
 *
 * Müşteri SSO: WHMCS ürün detayında ?dosinglesignon=1 (ServiceSingleSignOn).
 *
 * @see https://developers.whmcs.com/provisioning-modules/
 * @see https://developers.whmcs.com/provisioning-modules/usage-update/
 */

if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

function hostvim_MetaData()
{
    return [
        'DisplayName' => 'Hostvim Panel',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
    ];
}

function hostvim_ConfigOptions()
{
    return [
        'Hosting Package ID' => [
            'Type' => 'text',
            'Size' => '8',
            'Default' => '',
            'Description' => 'Hostvim panel: hosting_packages tablosundaki sayısal id (boş = paket atanmaz)',
        ],
        'PHP Version' => [
            'Type' => 'dropdown',
            'Options' => '8.2,8.3,8.4,8.1,8.0,7.4',
            'Default' => '8.2',
            'Description' => 'PHP-FPM sürümü (engine + vhost)',
        ],
        'Web Server' => [
            'Type' => 'dropdown',
            'Options' => 'nginx,apache,openlitespeed',
            'Default' => 'nginx',
            'Description' => 'Site ön uç (nginx/apache/OLS)',
        ],
        'Issue Lets Encrypt' => [
            'Type' => 'yesno',
            'Description' => 'Kurulumda DV sertifika dene (DNS doğru olmalı)',
        ],
    ];
}

function hostvim_TestConnection(array $params)
{
    try {
        hostvim_apiGet($params, 'test');

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function hostvim_CreateAccount(array $params)
{
    try {
        $email = hostvim_clientEmail($params);
        $name = hostvim_clientFullName($params);
        $password = (string) ($params['password'] ?? '');
        if ($password === '') {
            return 'Ürün şifresi boş; WHMCS otomatik şifre üretimini açın veya şifre girin.';
        }

        $domain = strtolower(trim((string) ($params['domain'] ?? '')));
        if ($domain === '') {
            return 'Alan adı (domain) boş; hosting ürününde birincil alan adı girilmeli.';
        }

        $pkg = hostvim_intOrNull($params['configoption1'] ?? null);
        $php = trim((string) ($params['configoption2'] ?? ''));
        if ($php === '') {
            $php = '8.2';
        }
        $server = trim((string) ($params['configoption3'] ?? ''));
        if ($server === '') {
            $server = 'nginx';
        }
        $issueSsl = hostvim_truthy($params['configoption4'] ?? '');

        $payload = [
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'domain' => $domain,
            'php_version' => $php,
            'server_type' => $server,
            'issue_lets_encrypt' => $issueSsl,
        ];
        if ($pkg !== null) {
            $payload['hosting_package_id'] = $pkg;
        }

        hostvim_apiPost($params, 'provision', $payload);

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function hostvim_SuspendAccount(array $params)
{
    try {
        hostvim_apiPost($params, 'suspend', ['email' => hostvim_clientEmail($params)]);

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function hostvim_UnsuspendAccount(array $params)
{
    try {
        hostvim_apiPost($params, 'unsuspend', ['email' => hostvim_clientEmail($params)]);

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function hostvim_TerminateAccount(array $params)
{
    try {
        hostvim_apiPost($params, 'terminate', [
            'email' => hostvim_clientEmail($params),
            'delete_sites' => true,
        ]);

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function hostvim_ChangePassword(array $params)
{
    try {
        $password = (string) ($params['password'] ?? '');
        if ($password === '') {
            return 'Yeni şifre boş.';
        }
        hostvim_apiPost($params, 'change-password', [
            'email' => hostvim_clientEmail($params),
            'password' => $password,
        ]);

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

function hostvim_ChangePackage(array $params)
{
    try {
        $pkg = hostvim_intOrNull($params['configoption1'] ?? null);
        $payload = ['email' => hostvim_clientEmail($params)];
        if ($pkg !== null) {
            $payload['hosting_package_id'] = $pkg;
        } else {
            $payload['hosting_package_id'] = null;
        }
        hostvim_apiPost($params, 'change-package', $payload);

        $domain = strtolower(trim((string) ($params['domain'] ?? '')));
        if ($domain !== '') {
            $php = trim((string) ($params['configoption2'] ?? ''));
            if ($php === '') {
                $php = '8.2';
            }
            $server = trim((string) ($params['configoption3'] ?? ''));
            if ($server === '') {
                $server = 'nginx';
            }
            hostvim_apiPost($params, 'site/update', [
                'email' => hostvim_clientEmail($params),
                'domain' => $domain,
                'php_version' => $php,
                'server_type' => $server,
            ]);
        }

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/**
 * Birincil barındırma alan adı değişimi (WHMCS Change Domain).
 * Parametreler: genelde domain = eski FQDN, newdomain = yeni FQDN (WHMCS sürümüne göre değişebilir).
 */
function hostvim_ChangeDomain(array $params)
{
    try {
        $new = strtolower(trim((string) ($params['newdomain'] ?? $params['new_domain'] ?? $params['newDomain'] ?? '')));
        $old = strtolower(trim((string) ($params['olddomain'] ?? $params['originaldomain'] ?? $params['domain'] ?? '')));
        if ($new === '') {
            $new = strtolower(trim((string) ($params['domain'] ?? '')));
            $old = strtolower(trim((string) ($params['olddomain'] ?? $params['originaldomain'] ?? '')));
        }
        if ($old === '' || $new === '') {
            return 'Alan adı değişimi: eski veya yeni FQDN eksik (WHMCS newdomain + domain/olddomain).';
        }
        hostvim_apiPost($params, 'change-domain', [
            'email' => hostvim_clientEmail($params),
            'old_domain' => $old,
            'new_domain' => $new,
        ], 180);

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/** Hizmet yenileme (faturalama); panelde denetim günlüğü. */
function hostvim_Renew(array $params)
{
    try {
        $domain = strtolower(trim((string) ($params['domain'] ?? '')));
        $payload = ['email' => hostvim_clientEmail($params)];
        if ($domain !== '') {
            $payload['domain'] = $domain;
        }
        hostvim_apiPost($params, 'service/renew', $payload);

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/**
 * Müşteri ürün sayfasında Hostvim SSO bağlantısı (templates/clientarea.tpl).
 *
 * @see https://developers.whmcs.com/provisioning-modules/single-sign-on/
 */
function hostvim_ClientArea(array $params)
{
    $sid = (int) ($params['serviceid'] ?? 0);

    return [
        'templatefile' => 'clientarea',
        'vars' => [
            'ssourl' => 'clientarea.php?action=productdetails&id='.$sid.'&dosinglesignon=1',
        ],
    ];
}

/**
 * WHMCS yönetim → Hostvim panel (sunucu kaydındaki “Kullanıcı adı” = admin e-postası).
 *
 * @see https://developers.whmcs.com/provisioning-modules/single-sign-on/
 */
function hostvim_AdminSingleSignOn(array $params)
{
    $return = [
        'success' => false,
    ];
    $response = null;
    $formatted = null;
    try {
        $email = strtolower(trim((string) ($params['serverusername'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Sunucu “Kullanıcı adı” alanına Hostvim admin e-postasını girin.');
        }
        $data = hostvim_apiPost($params, 'sso/mint-admin', ['email' => $email]);
        $url = trim((string) ($data['redirect_url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('Admin SSO: redirect_url eksik.');
        }
        $return = [
            'success' => true,
            'redirectTo' => $url,
        ];
        $response = $data;
        $formatted = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        $return['errorMsg'] = $e->getMessage();
        $response = $e->getMessage();
        $formatted = $e->getMessage();
    }

    if (function_exists('logModuleCall')) {
        logModuleCall('hostvim', __FUNCTION__, $params, $response, $formatted, ['serverpassword']);
    }

    return $return;
}

function hostvim_ServiceSingleSignOn(array $params)
{
    $return = [
        'success' => false,
    ];
    $response = null;
    $formatted = null;
    try {
        $email = hostvim_clientEmail($params);
        $data = hostvim_apiPost($params, 'sso/mint', ['email' => $email]);
        $url = trim((string) ($data['redirect_url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('SSO: redirect_url eksik.');
        }
        $return = [
            'success' => true,
            'redirectTo' => $url,
        ];
        $response = $data;
        $formatted = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        $return['errorMsg'] = $e->getMessage();
        $response = $e->getMessage();
        $formatted = $e->getMessage();
    }

    if (function_exists('logModuleCall')) {
        logModuleCall('hostvim', __FUNCTION__, $params, $response, $formatted, ['serverpassword']);
    }

    return $return;
}

function hostvim_UsageUpdate(array $params)
{
    try {
        $serverId = (int) ($params['serverid'] ?? 0);
        if ($serverId <= 0) {
            return 'Geçersiz sunucu kimliği.';
        }

        $data = hostvim_apiGet($params, 'usage/accounts');
        $accounts = $data['accounts'] ?? [];
        if (! is_array($accounts)) {
            return 'usage/accounts beklenmeyen yanıt.';
        }

        foreach ($accounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $domain = strtolower(trim((string) ($row['domain'] ?? '')));
            if ($domain === '') {
                continue;
            }

            \WHMCS\Database\Capsule::table('tblhosting')
                ->where('server', $serverId)
                ->where('domain', $domain)
                ->update([
                    'diskusage' => (int) ($row['diskusage'] ?? 0),
                    'disklimit' => (int) ($row['disklimit'] ?? 0),
                    'bwusage' => (int) ($row['bandwidth'] ?? 0),
                    'bwlimit' => (int) ($row['bwlimit'] ?? 0),
                    'lastupdate' => \WHMCS\Database\Capsule::raw('now()'),
                ]);
        }

        return 'success';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/* -------------------------------------------------------------------------
 * Yardımcılar
 * ------------------------------------------------------------------------- */

function hostvim_clientEmail(array $params): string
{
    $e = trim((string) ($params['clientsdetails']['email'] ?? ''));
    if ($e === '') {
        throw new RuntimeException('WHMCS müşteri e-postası boş; panel kullanıcısı e-posta ile eşlenir.');
    }

    return $e;
}

function hostvim_clientFullName(array $params): string
{
    $c = $params['clientsdetails'] ?? [];
    $first = trim((string) ($c['firstname'] ?? ''));
    $last = trim((string) ($c['lastname'] ?? ''));
    $n = trim($first.' '.$last);
    if ($n !== '') {
        return $n;
    }
    $company = trim((string) ($c['companyname'] ?? ''));

    return $company !== '' ? $company : 'Hosting Customer';
}

function hostvim_intOrNull($v): ?int
{
    if ($v === null || $v === '') {
        return null;
    }
    if (is_numeric($v)) {
        return (int) $v;
    }

    return null;
}

function hostvim_truthy($v): bool
{
    $s = strtolower(trim((string) $v));

    return in_array($s, ['1', 'yes', 'y', 'on', 'true'], true);
}

function hostvim_apiBase(array $params): string
{
    $raw = trim((string) ($params['serverhostname'] ?? ''));
    if ($raw === '') {
        $raw = trim((string) ($params['serverip'] ?? ''));
    }
    $raw = rtrim($raw, '/');
    if ($raw === '') {
        throw new RuntimeException('Sunucu Hostname veya IP dolu olmalı.');
    }
    if (! preg_match('#^https?://#i', $raw)) {
        $secure = ! empty($params['serversecure']);
        $raw = ($secure ? 'https://' : 'http://').$raw;
    }

    return $raw.'/index.php/api/integrations/whmcs';
}

function hostvim_bearerSecret(array $params): string
{
    $s = (string) ($params['serverpassword'] ?? '');
    if ($s === '') {
        throw new RuntimeException('Sunucu şifresi (HOSTVIM_WHMCS_SECRET) tanımlı değil.');
    }

    return $s;
}

function hostvim_apiGet(array $params, string $path): array
{
    $url = hostvim_apiBase($params).'/'.ltrim($path, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('cURL başlatılamadı.');
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer '.hostvim_bearerSecret($params),
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('HTTP isteği başarısız: '.$err);
    }

    return hostvim_parseJsonResponse($body, $code);
}

function hostvim_apiPost(array $params, string $path, array $payload, int $timeoutSeconds = 120): array
{
    $url = hostvim_apiBase($params).'/'.ltrim($path, '/');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON kodlama hatası.');
    }
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('cURL başlatılamadı.');
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer '.hostvim_bearerSecret($params),
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('HTTP isteği başarısız: '.$err);
    }

    return hostvim_parseJsonResponse($body, $code);
}

function hostvim_parseJsonResponse(string $body, int $httpCode): array
{
    $data = json_decode($body, true);
    if (! is_array($data)) {
        throw new RuntimeException('Geçersiz JSON yanıt (HTTP '.$httpCode.'): '.substr($body, 0, 500));
    }
    if ($httpCode >= 400) {
        $msg = (string) ($data['message'] ?? json_encode($data));
        throw new RuntimeException($msg.' (HTTP '.$httpCode.')');
    }

    return $data;
}
