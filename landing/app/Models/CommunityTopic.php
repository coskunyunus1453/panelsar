<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityTopic extends Model
{
    public const STATUS_PUBLISHED = 'published';

    public const STATUS_HIDDEN = 'hidden';

    public const MODERATION_APPROVED = 'approved';

    public const MODERATION_PENDING = 'pending';

    public const MODERATION_REJECTED = 'rejected';

    protected $fillable = [
        'community_category_id',
        'user_id',
        'title',
        'slug',
        'body',
        'excerpt',
        'status',
        'is_locked',
        'is_pinned',
        'is_solved',
        'best_answer_post_id',
        'view_count',
        'meta_title',
        'meta_description',
        'canonical_url',
        'robots_override',
        'last_activity_at',
        'moderation_status',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'is_pinned' => 'boolean',
            'is_solved' => 'boolean',
            'view_count' => 'integer',
            'last_activity_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CommunityCategory::class, 'community_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommunityPost::class, 'community_topic_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            CommunityTag::class,
            'community_tag_topic',
            'community_topic_id',
            'community_tag_id'
        )->withTimestamps();
    }

    public function visiblePosts(): HasMany
    {
        return $this->posts()->where('is_hidden', false)->orderBy('created_at');
    }

    public function bestAnswer(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'best_answer_post_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeModerationApproved($query)
    {
        return $query->where('moderation_status', self::MODERATION_APPROVED);
    }
}
