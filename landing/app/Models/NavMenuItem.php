<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class NavMenuItem extends Model
{
    public const ZONE_HEADER = 'header';

    public const ZONE_FOOTER = 'footer';

    protected $fillable = [
        'zone',
        'label',
        'href',
        'sort_order',
        'is_active',
        'open_in_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return Builder<NavMenuItem>
     */
    public function scopeActiveForZone(Builder $query, string $zone): Builder
    {
        return $query->where('zone', $zone)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function resolvedHref(): string
    {
        $raw = trim($this->href);
        if ($raw === '') {
            return URL::to('/');
        }

        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        if (! str_starts_with($raw, '/')) {
            $raw = '/'.$raw;
        }

        if (str_contains($raw, '..')) {
            return URL::to('/');
        }

        $parts = explode('#', $raw, 2);
        $path = $parts[0] !== '' ? $parts[0] : '/';
        $base = URL::to($path);

        if (isset($parts[1]) && $parts[1] !== '') {
            return $base.'#'.$parts[1];
        }

        return $base;
    }
}
