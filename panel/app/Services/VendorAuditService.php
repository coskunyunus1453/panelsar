<?php

namespace App\Services;

use App\Models\VendorAuditEvent;
use Illuminate\Http\Request;

class VendorAuditService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $event,
        string $severity = 'info',
        ?int $tenantId = null,
        ?int $licenseId = null,
        ?int $actorUserId = null,
        array $payload = [],
        ?Request $request = null,
    ): void {
        $ip = $request?->ip();
        $ipStored = $ip !== null && $ip !== ''
            ? hash('sha256', (string) $ip)
            : null;

        $ua = $request?->userAgent();
        $uaStored = $ua !== null && $ua !== ''
            ? mb_substr($ua, 0, 255)
            : null;

        VendorAuditEvent::query()->create([
            'tenant_id' => $tenantId,
            'license_id' => $licenseId,
            'actor_user_id' => $actorUserId,
            'event' => $event,
            'severity' => $severity,
            'ip_address' => $ipStored,
            'user_agent' => $uaStored,
            'payload' => SafeAuditLogger::sanitizeContext($payload),
        ]);
    }
}
