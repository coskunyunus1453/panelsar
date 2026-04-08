<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** is_admin yalnızca forceFill / doğrudan atama ile (kitle atamayla yetki yükseltmesi engellenir) */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'community_banned_at' => 'datetime',
            'community_shadowbanned_at' => 'datetime',
        ];
    }

    public function communityTopics(): HasMany
    {
        return $this->hasMany(CommunityTopic::class);
    }

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function isCommunityBanned(): bool
    {
        return $this->community_banned_at !== null;
    }

    public function isCommunityShadowBanned(): bool
    {
        return $this->community_shadowbanned_at !== null;
    }
}
