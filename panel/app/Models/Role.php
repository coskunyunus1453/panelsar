<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'is_system',
        'assignable_by_reseller',
        'display_name',
        'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'assignable_by_reseller' => 'boolean',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
