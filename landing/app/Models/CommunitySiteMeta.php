<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunitySiteMeta extends Model
{
    protected $table = 'community_site_meta';

    protected $fillable = [
        'site_title',
        'site_title_en',
        'default_meta_title',
        'default_meta_title_en',
        'default_meta_description',
        'default_meta_description_en',
        'og_image_url',
        'twitter_site',
        'enable_indexing',
        'moderation_new_topics',
        'moderation_new_posts',
    ];

    protected function casts(): array
    {
        return [
            'enable_indexing' => 'boolean',
            'moderation_new_topics' => 'boolean',
            'moderation_new_posts' => 'boolean',
        ];
    }

    public static function singleton(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'site_title' => 'Topluluk',
            'site_title_en' => 'Community',
            'enable_indexing' => true,
            'moderation_new_topics' => false,
            'moderation_new_posts' => false,
        ]);
    }

    public function displaySiteTitle(): string
    {
        if (app()->getLocale() === 'en') {
            if (filled($this->site_title_en)) {
                return (string) $this->site_title_en;
            }

            return landing_t('community.fallback_site_title_short');
        }

        return (string) $this->site_title;
    }

    public function displayDefaultMetaTitle(): ?string
    {
        if (app()->getLocale() === 'en') {
            return filled($this->default_meta_title_en) ? (string) $this->default_meta_title_en : null;
        }

        return filled($this->default_meta_title) ? (string) $this->default_meta_title : null;
    }

    public function displayDefaultMetaDescription(): string
    {
        if (app()->getLocale() === 'en') {
            return filled($this->default_meta_description_en) ? (string) $this->default_meta_description_en : '';
        }

        return (string) ($this->default_meta_description ?? '');
    }
}
