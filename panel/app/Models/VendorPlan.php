<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'billing_cycle',
        'price_minor',
        'currency',
        'is_public',
        'limits',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'limits' => 'array',
    ];

    public function planFeatures(): HasMany
    {
        return $this->hasMany(VendorPlanFeature::class, 'plan_id');
    }
}

