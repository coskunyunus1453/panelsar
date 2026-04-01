<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_id',
        'provider',
        'external_id',
        'status',
        'amount_minor',
        'currency',
        'billing_cycle',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(VendorTenant::class, 'tenant_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(VendorLicense::class, 'license_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class, 'subscription_id');
    }
}

