<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPlanFeature extends Model
{
    protected $fillable = [
        'plan_id',
        'feature_id',
        'enabled',
        'quota',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(VendorPlan::class, 'plan_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(VendorFeature::class, 'feature_id');
    }
}

