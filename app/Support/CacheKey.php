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

    /**
     * Combined version token for payloads that embed several models, read in one cache round-trip.
     *
     * @param  list<string>  $keys
     */
    public static function versionedMany(array $keys): string
    {
        $versionKeys = array_map(fn (string $key) => self::versionKey($key), $keys);
        $values = Cache::many($versionKeys);

        return implode('.', array_map(fn (string $versionKey) => (int) ($values[$versionKey] ?? 1), $versionKeys));
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
