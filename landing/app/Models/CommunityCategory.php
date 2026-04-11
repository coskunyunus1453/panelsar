<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'name_en',
        'description',
        'description_en',
        'sort_order',
        'is_active',
        'meta_title',
        'meta_title_en',
        'meta_description',
        'meta_description_en',
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

    public function displayName(): string
    {
        if (app()->getLocale() === 'en' && filled($this->name_en)) {
            return (string) $this->name_en;
        }

        return (string) $this->name;
    }

    public function displayMetaTitle(): ?string
    {
        if (app()->getLocale() === 'en') {
            return filled($this->meta_title_en) ? (string) $this->meta_title_en : null;
        }

        return filled($this->meta_title) ? (string) $this->meta_title : null;
    }

    public function displayMetaDescription(): string
    {
        if (app()->getLocale() === 'en') {
            return filled($this->meta_description_en) ? (string) $this->meta_description_en : '';
        }

        return (string) ($this->meta_description ?? '');
    }
}
