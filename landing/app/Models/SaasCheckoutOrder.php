<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasCheckoutOrder extends Model
{
    protected $fillable = [
        'order_ref',
        'provider',
        'locale',
        'email',
        'name',
        'phone',
        'saas_license_product_id',
        'amount_minor',
        'currency',
        'status',
        'stripe_checkout_session_id',
        'saas_license_id',
        'paid_at',
        'failure_note',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SaasLicenseProduct::class, 'saas_license_product_id');
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(SaasLicense::class, 'saas_license_id');
    }
}
