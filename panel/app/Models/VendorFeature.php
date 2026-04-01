<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorFeature extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'kind',
    ];

    public function planFeatures(): HasMany
    {
        return $this->hasMany(VendorPlanFeature::class, 'feature_id');
    }
}

