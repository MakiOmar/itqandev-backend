<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CacheKey
{
    public static function versioned(string $key): string
    {
        $version = Cache::get(self::versionKey($key), 1);

        return "{$key}:v{$version}";
    }

    public static function bump(string $key): void
    {
        self::incrementVersion(self::versionKey($key));
    }

    /**
     * Atomic version bump: `add` seeds a missing counter (stores refuse to increment
     * missing keys), `increment` is safe under concurrent writers.
     */
    public static function incrementVersion(string $versionKey): void
    {
        Cache::add($versionKey, 1);
        Cache::increment($versionKey);
    }

    protected static function versionKey(string $key): string
    {
        return "v:{$key}";
    }
}
