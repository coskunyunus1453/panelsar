<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LandingSiteSetting extends Model
{
    protected $table = 'landing_site_settings';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $bag = Cache::remember('landing_site_settings_map', 600, function () {
            return static::query()->pluck('value', 'key')->all();
        });

        $v = $bag[$key] ?? null;

        return $v !== null && $v !== '' ? $v : $default;
    }

    public static function forgetCache(): void
    {
        Cache::forget('landing_site_settings_map');
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        static::forgetCache();
    }
}
