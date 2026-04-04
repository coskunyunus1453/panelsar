<?php

namespace App\Models;

use App\Services\LandingI18n;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LandingTranslation extends Model
{
    protected $fillable = ['locale', 'key', 'value'];

    protected static function booted(): void
    {
        static::saved(function (LandingTranslation $row): void {
            static::forgetCacheFor($row->locale);
            LandingI18n::clearRuntimeCache();
        });
        static::deleted(function (LandingTranslation $row): void {
            static::forgetCacheFor($row->locale);
            LandingI18n::clearRuntimeCache();
        });
    }

    public static function forgetCacheFor(?string $locale = null): void
    {
        if ($locale === null) {
            Cache::forget('landing_translation_overrides');

            return;
        }
        Cache::forget('landing_translation_overrides_'.$locale);
    }

    public static function overridesFor(string $locale): array
    {
        return Cache::remember('landing_translation_overrides_'.$locale, 600, function () use ($locale) {
            return static::query()
                ->where('locale', $locale)
                ->pluck('value', 'key')
                ->all();
        });
    }
}
