<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hosting_package_id',
        'stripe_subscription_id',
        'payment_provider',
        'external_subscription_id',
        'status',
        'billing_cycle',
        'amount',
        'currency',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hostingPackage()
    {
        return $this->belongsTo(HostingPackage::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Stripe: trialing, active veya ödeme gecikmesinde (past_due) barındırma kotası atanır;
     * süresi bitmiş (ends_at) kayıtlar hariç.
     */
    public function grantsHostingPackage(): bool
    {
        if (! in_array($this->status, ['trialing', 'active', 'past_due'], true)) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    public function scopeGrantingHostingPackage(Builder $query): Builder
    {
        return $query->whereIn('status', ['trialing', 'active', 'past_due'])
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }
}
