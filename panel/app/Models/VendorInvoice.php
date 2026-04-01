<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorInvoice extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'provider',
        'external_id',
        'status',
        'amount_minor',
        'currency',
        'due_at',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(VendorTenant::class, 'tenant_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(VendorSubscription::class, 'subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class, 'invoice_id');
    }
}

