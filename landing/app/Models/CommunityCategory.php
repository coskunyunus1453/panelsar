<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
        'is_active',
        'meta_title',
        'meta_description',
        'robots_override',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function topics(): HasMany
    {
        return $this->hasMany(CommunityTopic::class, 'community_category_id');
    }
}
