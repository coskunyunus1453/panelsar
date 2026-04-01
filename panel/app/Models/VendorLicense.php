<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorLicense extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'license_key',
        'status',
        'starts_at',
        'expires_at',
        'last_verified_at',
        'constraints',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'constraints' => 'array',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(VendorTenant::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(VendorPlan::class, 'plan_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(VendorNode::class, 'license_id');
    }
}

