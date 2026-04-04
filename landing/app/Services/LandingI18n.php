<?php

namespace App\Services;

use App\Models\LandingTranslation;

class LandingI18n
{
    /** @var array<string, array<string, string>> */
    private static array $overrideBags = [];

    public function line(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $bag = self::$overrideBags[$locale] ??= LandingTranslation::overridesFor($locale);
        if (array_key_exists($key, $bag) && $bag[$key] !== null && $bag[$key] !== '') {
            $line = $bag[$key];
        } else {
            $line = trans('landing.'.$key, [], $locale);
        }

        if ($replace !== []) {
            foreach ($replace as $k => $v) {
                $line = str_replace([':'.$k, ':'.strtoupper($k), ':'.ucfirst($k)], [$v, $v, $v], $line);
            }
        }

        return $line;
    }

    public static function clearRuntimeCache(): void
    {
        self::$overrideBags = [];
    }
}
