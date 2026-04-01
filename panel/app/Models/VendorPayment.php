<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'provider',
        'external_id',
        'status',
        'amount_minor',
        'currency',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(VendorTenant::class, 'tenant_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class, 'invoice_id');
    }
}

