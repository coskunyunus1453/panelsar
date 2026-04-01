<?php

namespace App\Services;

use App\Models\VendorLicense;
use App\Models\VendorNode;

class VendorLicenseService
{
    /**
     * @return array<string, mixed>
     */
    public function buildLicensePayload(VendorLicense $license, ?VendorNode $node = null): array
    {
        $license->loadMissing(['plan.planFeatures.feature', 'tenant']);
        $features = [];
        foreach ($license->plan->planFeatures as $pf) {
            $key = (string) $pf->feature->key;
            $features[$key] = [
                'enabled' => (bool) $pf->enabled,
                'quota' => $pf->quota !== null ? (int) $pf->quota : null,
            ];
        }

        return [
            'license_id' => (int) $license->id,
            'license_key' => (string) $license->license_key,
            'status' => (string) $license->status,
            'tenant' => [
                'id' => (int) $license->tenant->id,
                'name' => (string) $license->tenant->name,
                'slug' => (string) $license->tenant->slug,
            ],
            'plan' => [
                'id' => (int) $license->plan->id,
                'code' => (string) $license->plan->code,
                'name' => (string) $license->plan->name,
                'limits' => $license->plan->limits ?? new \stdClass,
            ],
            'features' => $features,
            'starts_at' => optional($license->starts_at)->toIso8601String(),
            'expires_at' => optional($license->expires_at)->toIso8601String(),
            'node' => $node ? [
                'id' => (int) $node->id,
                'instance_id' => (string) $node->instance_id,
                'hostname' => (string) ($node->hostname ?? ''),
                'status' => (string) $node->status,
            ] : null,
            'issued_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function signPayload(array $payload): string
    {
        $secret = (string) config('panelsar.vendor_license_signing_key', '');
        if ($secret === '') {
            $secret = hash('sha256', (string) config('app.key', 'panelsar-default-key'));
        }

        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $secret);
    }

    public function isLicenseUsable(VendorLicense $license): bool
    {
        if ($license->status !== 'active') {
            return false;
        }
        if ($license->starts_at && $license->starts_at->isFuture()) {
            return false;
        }
        if ($license->expires_at && $license->expires_at->isPast()) {
            $graceHours = max(0, (int) config('panelsar.vendor_license_grace_hours', 24));
            if ($graceHours <= 0 || $license->expires_at->copy()->addHours($graceHours)->isPast()) {
                return false;
            }
        }

        return true;
    }
}

