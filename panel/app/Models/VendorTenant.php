<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorTenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'panel_user_id',
        'status',
        'contact_email',
        'country',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function licenses(): HasMany
    {
        return $this->hasMany(VendorLicense::class, 'tenant_id');
    }

    public function panelUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'panel_user_id');
    }
}

