<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasLicenseProduct extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'default_limits',
        'default_modules',
        'is_active',
        'sort_order',
        'price_try_minor',
        'price_usd_minor',
        'price_eur_minor',
    ];

    protected function casts(): array
    {
        return [
            'default_limits' => 'array',
            'default_modules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(SaasLicense::class, 'saas_license_product_id');
    }
}
