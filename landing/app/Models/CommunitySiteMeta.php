<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunitySiteMeta extends Model
{
    protected $table = 'community_site_meta';

    protected $fillable = [
        'site_title',
        'default_meta_title',
        'default_meta_description',
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
            'enable_indexing' => true,
            'moderation_new_topics' => false,
            'moderation_new_posts' => false,
        ]);
    }
}
