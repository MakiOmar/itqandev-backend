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
        Cache::increment(self::versionKey($key));
    }

    protected static function versionKey(string $key): string
    {
        return "v:{$key}";
    }
}

