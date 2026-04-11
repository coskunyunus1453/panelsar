<?php
/**
 * Örnek WHMCS hook — Hostvim panel API (Bearer HOSTVIM_WHMCS_SECRET).
 *
 * Kurulum:
 * - Bu dosyayı WHMCS kökünde `includes/hooks/` altına kopyalayın.
 * - Aşağıdaki sabitleri kendi ortamınıza göre doldurun (tercihen ortam değişkeni / şifre kasası).
 *
 * Uçlar: POST {PANEL}/api/integrations/whmcs/ssl/issue, backup/queue, dns/import-zone vb.
 */
if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

/** @var string Panel kök URL, sondaki / olmadan */
const HOSTVIM_PANEL_URL = 'https://panel.example.com';

/** @var string Panel .env HOSTVIM_WHMCS_SECRET ile aynı */
const HOSTVIM_WHMCS_SECRET = 'CHANGE_ME';

/**
 * Hizmet oluşturulduktan sonra Let’s Encrypt tetikleme örneği.
 * Gerçek projede müşteri e-postası ve alan adını $vars içinden güvenilir şekilde eşleştirin.
 */
add_hook('AfterModuleCreate', 1, function (array $vars) {
    $email = trim((string) ($vars['clientsdetails']['email'] ?? ''));
    $domain = trim((string) ($vars['domain'] ?? ''));
    if ($email === '' || $domain === '' || HOSTVIM_WHMCS_SECRET === 'CHANGE_ME') {
        return;
    }

    $url = HOSTVIM_PANEL_URL.'/api/integrations/whmcs/ssl/issue';
    $payload = json_encode([
        'email' => $email,
        'domain' => $domain,
    ], JSON_THROW_ON_ERROR);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer '.HOSTVIM_WHMCS_SECRET,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    curl_close($ch);
});
