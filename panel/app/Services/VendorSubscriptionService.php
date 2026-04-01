<?php

namespace App\Services;

use App\Models\VendorLicense;
use App\Models\VendorSubscription;

class VendorSubscriptionService
{
    public function reconcileLicenseStatus(VendorSubscription $subscription): void
    {
        if (! $subscription->license_id) {
            return;
        }

        $license = VendorLicense::query()->find($subscription->license_id);
        if (! $license) {
            return;
        }

        $status = strtolower((string) $subscription->status);
        $next = in_array($status, ['active', 'trialing'], true) ? 'active' : 'suspended';
        if ($license->status !== $next) {
            $license->status = $next;
            $license->save();
        }
    }
}

