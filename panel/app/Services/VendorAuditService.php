<?php

namespace App\Services;

use App\Models\VendorAuditEvent;
use Illuminate\Http\Request;

class VendorAuditService
{
    /**
     * @param array<string, mixed> $payload
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
        VendorAuditEvent::query()->create([
            'tenant_id' => $tenantId,
            'license_id' => $licenseId,
            'actor_user_id' => $actorUserId,
            'event' => $event,
            'severity' => $severity,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'payload' => $payload,
        ]);
    }
}

