<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PluginModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'summary',
        'category',
        'version',
        'is_paid',
        'price_cents',
        'currency',
        'is_public',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_public' => 'boolean',
            'config' => 'array',
        ];
    }
}
