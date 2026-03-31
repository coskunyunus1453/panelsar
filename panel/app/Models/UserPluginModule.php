<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPluginModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plugin_module_id',
        'status',
        'is_active',
        'installed_at',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'installed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function plugin()
    {
        return $this->belongsTo(PluginModule::class, 'plugin_module_id');
    }
}
