<?php

namespace App\Concerns;

use App\Support\CacheKey;

trait RefreshesCache
{
    protected static function bootRefreshesCache(): void
    {
        static::saved(function () {
            CacheKey::bump(static::class);
        });

        static::deleted(function () {
            CacheKey::bump(static::class);
        });
    }
}

