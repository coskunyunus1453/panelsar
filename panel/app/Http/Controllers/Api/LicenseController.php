<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EngineApiService;
use App\Services\LicenseHubClient;
use App\Services\PanelStoredLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        private EngineApiService $engine,
        private LicenseHubClient $licenseHub,
        private PanelStoredLicenseService $storedLicense,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $key = $this->storedLicense->effectiveKey() ?: (string) $request->query('key', '');
        $key = trim($key);
        $keySource = $this->storedLicense->keySource();
        $hubBase = rtrim(trim((string) config('hostvim.license_server', '')), '/');
        $hubConfigured = $hubBase !== '';

        $hub = $key !== '' ? $this->licenseHub->validate($key) : [];
        if ($hub !== []) {
            return response()->json([
                'local_key_set' => $key !== '',
                'key_source' => $keySource,
                'key_preview' => $key !== '' ? PanelStoredLicenseService::maskKey($key) : null,
                'hub_configured' => $hubConfigured,
                'source' => 'license_server',
                'hub' => $hub,
                'engine' => null,
            ]);
        }

        return response()->json([
            'local_key_set' => $key !== '',
            'key_source' => $keySource,
            'key_preview' => $key !== '' ? PanelStoredLicenseService::maskKey($key) : null,
            'hub_configured' => $hubConfigured,
            'source' => 'engine',
            'hub' => null,
            'engine' => $key !== '' ? $this->engine->validateLicense($key) : null,
        ]);
    }

    /**
     * Anahtarı kaydetmeden doğrulama (admin testi).
     */
    public function validateWithKey(Request $request): JsonResponse
    {
        $validated = $request->validate(['key' => ['required', 'string', 'max:128']]);
        $key = trim($validated['key']);
        $hub = $this->licenseHub->validate($key);
        if ($hub !== []) {
            return response()->json($hub);
        }

        return response()->json($this->engine->validateLicense($key));
    }

    /**
     * Merkezi hub ile doğrular ve geçerliyse anahtarı panel veritabanında şifreli saklar (.env gerekmez).
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate(['key' => ['required', 'string', 'max:128']]);
        $key = trim($validated['key']);
        $base = rtrim(trim((string) config('hostvim.license_server', '')), '/');
        if ($base === '') {
            return response()->json([
                'message' => 'License hub URL is not configured on this server.',
            ], 422);
        }

        $hub = $this->licenseHub->validate($key);
        if ($hub === []) {
            return response()->json([
                'message' => 'Could not reach the license server. Check network, firewall, or try again later.',
            ], 503);
        }

        if (! ($hub['valid'] ?? false)) {
            return response()->json([
                'message' => (string) ($hub['message'] ?? 'Invalid license key'),
                'code' => $hub['code'] ?? 'invalid',
            ], 422);
        }

        try {
            $this->storedLicense->store($key);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not save license key.'], 500);
        }

        return response()->json([
            'ok' => true,
            'hub' => $hub,
            'key_source' => $this->storedLicense->keySource(),
            'key_preview' => PanelStoredLicenseService::maskKey($key),
        ]);
    }

    public function clearStored(): JsonResponse
    {
        if ($this->storedLicense->keySource() === 'env') {
            return response()->json([
                'message' => 'License key is set via server environment (LICENSE_KEY). Remove it from .env to clear.',
            ], 422);
        }

        $this->storedLicense->clearStored();

        return response()->json(['ok' => true]);
    }
}
