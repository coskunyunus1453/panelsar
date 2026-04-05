<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasLicense extends Model
{
    protected $fillable = [
        'license_key',
        'saas_customer_id',
        'saas_license_product_id',
        'status',
        'starts_at',
        'expires_at',
        'limits_override',
        'modules_override',
        'subscription_status',
        'subscription_renews_at',
        'billing_provider',
        'billing_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'subscription_renews_at' => 'datetime',
            'limits_override' => 'array',
            'modules_override' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(SaasCustomer::class, 'saas_customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SaasLicenseProduct::class, 'saas_license_product_id');
    }
}
