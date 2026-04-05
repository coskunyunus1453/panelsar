<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasProductModule extends Model
{
    protected $fillable = [
        'key', 'label', 'description', 'is_paid', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
