<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorSupportTicket extends Model
{
    protected $fillable = [
        'tenant_id',
        'license_id',
        'created_by_user_id',
        'subject',
        'status',
        'priority',
        'last_message',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(VendorTenant::class, 'tenant_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(VendorLicense::class, 'license_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(VendorSupportMessage::class, 'ticket_id');
    }
}

