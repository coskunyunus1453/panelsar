<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsItem extends Model
{
    protected $table = 'cms_items';

    protected $fillable = [
        'kind',
        'slug',
        'locale',
        'title',
        'excerpt',
        'body_markdown',
        'featured',
        'section',
        'meta_title',
        'meta_description',
        'status',
        'published_at',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public const KIND_LANDING = 'landing';

    public const KIND_INSTALL = 'install';

    public const KIND_DOC = 'doc';

    public const KIND_BLOG = 'blog';

    public static function kinds(): array
    {
        return [
            self::KIND_LANDING,
            self::KIND_INSTALL,
            self::KIND_DOC,
            self::KIND_BLOG,
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('is_published', true);
    }
}
