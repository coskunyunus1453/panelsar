<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * İsteğe bağlı CDN (şimdilik Cloudflare purge_everything).
 */
class CdnIntegrationService
{
    public function provider(): string
    {
        return strtolower(trim((string) config('hostvim.cdn.provider', '')));
    }

    public function isCloudflareConfigured(): bool
    {
        if ($this->provider() !== 'cloudflare') {
            return false;
        }
        $token = trim((string) config('hostvim.cdn.api_token', ''));
        $zone = trim((string) config('hostvim.cdn.zone_id', ''));

        return $token !== '' && $zone !== '';
    }

    /**
     * @return array{ok: bool, error?: string, detail?: mixed}
     */
    public function purgeEverything(): array
    {
        if (! $this->isCloudflareConfigured()) {
            return ['ok' => false, 'error' => 'cdn_not_configured'];
        }
        $token = trim((string) config('hostvim.cdn.api_token'));
        $zone = trim((string) config('hostvim.cdn.zone_id'));
        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.cloudflare.com/client/v4/zones/'.$zone.'/purge_cache', [
                    'purge_everything' => true,
                ]);
            $json = $response->json();
            if ($response->successful() && ($json['success'] ?? false) === true) {
                return ['ok' => true, 'detail' => $json];
            }
            $msg = is_array($json['errors'][0] ?? null)
                ? (string) ($json['errors'][0]['message'] ?? 'cloudflare_error')
                : 'cloudflare_error';
            Log::warning('CDN purge failed', ['status' => $response->status(), 'body' => $response->body()]);

            return ['ok' => false, 'error' => $msg, 'detail' => $json];
        } catch (\Throwable $e) {
            Log::error('CDN purge exception: '.$e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
