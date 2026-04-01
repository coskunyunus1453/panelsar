<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorNode extends Model
{
    protected $fillable = [
        'license_id',
        'instance_id',
        'fingerprint',
        'hostname',
        'public_ip',
        'agent_version',
        'status',
        'last_seen_at',
        'capabilities',
        'meta',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'capabilities' => 'array',
        'meta' => 'array',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(VendorLicense::class, 'license_id');
    }
}

