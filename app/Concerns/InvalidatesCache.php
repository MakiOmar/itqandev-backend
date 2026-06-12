<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Cache;

trait InvalidatesCache
{
    /**
     * Get cache keys to invalidate for this model.
     */
    protected function getCacheKeys(): array
    {
        $modelName = strtolower(class_basename(static::class));
        return [
            "{$modelName}s:list",
            "{$modelName}:*", // Wildcard for any model-specific cache
        ];
    }

    /**
     * Invalidate all cache keys for this model.
     */
    public function invalidateCache(): void
    {
        $keys = $this->getCacheKeys();
        
        foreach ($keys as $key) {
            if (str_contains($key, '*')) {
                // Handle wildcard keys if cache driver supports tags
                if (method_exists(Cache::getStore(), 'supportsTags') && Cache::getStore()->supportsTags()) {
                    $tag = str_replace(':*', '', $key);
                    Cache::tags([$tag])->flush();
                }
                // Non-tag drivers: skip wildcard keys instead of flushing the entire cache store.
            } else {
                Cache::forget($key);
            }
        }
    }

    /**
     * Boot the trait and set up cache invalidation on model events.
     */
    protected static function bootInvalidatesCache(): void
    {
        static::saved(function ($model) {
            $model->invalidateCache();
        });

        static::deleted(function ($model) {
            $model->invalidateCache();
        });
    }
}
