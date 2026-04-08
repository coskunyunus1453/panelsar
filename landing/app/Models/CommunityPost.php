<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPost extends Model
{
    public const MODERATION_APPROVED = 'approved';

    public const MODERATION_PENDING = 'pending';

    public const MODERATION_REJECTED = 'rejected';

    protected $fillable = [
        'community_topic_id',
        'user_id',
        'body',
        'is_hidden',
        'moderation_status',
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CommunityTopic::class, 'community_topic_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
