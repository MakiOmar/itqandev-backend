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
        // Prefer forever+get over increment: missing keys would otherwise stay on default v1.
        $current = (int) Cache::get(self::versionKey($key), 1);
        Cache::forever(self::versionKey($key), $current + 1);
    }

    protected static function versionKey(string $key): string
    {
        return "v:{$key}";
    }
}

