<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasCustomer extends Model
{
    protected $fillable = [
        'name', 'email', 'company', 'phone', 'status', 'notes',
    ];

    public function licenses(): HasMany
    {
        return $this->hasMany(SaasLicense::class, 'saas_customer_id');
    }
}
