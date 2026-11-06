<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache helpers for unauthenticated payloads that may legitimately be "not found".
 *
 * Cache::remember() stores a null result but reads it back as a miss, so every request for an
 * unknown slug would rebuild (and rewrite) the entry. A missing record is cached as a marker instead.
 */
final class PublicCache
{
    /** Short, so a record published without a cache-version bump still appears quickly. */
    public const MISSING_TTL_SECONDS = 60;

    private const MISSING_MARKER = '__public_cache_missing';

    /**
     * @param  Closure(): (array<string, mixed>|null)  $build  Returns null when the record does not exist.
     * @return array<string, mixed>|null Null when missing (callers respond 404).
     */
    public static function rememberOrMissing(string $key, int $ttlSeconds, Closure $build): ?array
    {
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return isset($cached[self::MISSING_MARKER]) ? null : $cached;
        }

        $payload = $build();
        if ($payload === null) {
            Cache::put($key, [self::MISSING_MARKER => true], self::MISSING_TTL_SECONDS);

            return null;
        }

        Cache::put($key, $payload, $ttlSeconds);

        return $payload;
    }
}
