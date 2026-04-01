<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAuditEvent extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_id',
        'actor_user_id',
        'event',
        'severity',
        'ip_address',
        'user_agent',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(VendorTenant::class, 'tenant_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(VendorLicense::class, 'license_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

