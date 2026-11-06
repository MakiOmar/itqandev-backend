<?php

namespace App\Services\ContentExport;

use App\Models\Category;
use App\Models\Project;
use App\Support\CacheKey;

final class CategoryListCacheInvalidator
{
    public const LIST_CACHE_KEY = 'categories:list:v3:json';

    public const PUBLIC_LIST_CACHE_KEY = 'public:categories:v1';

    /**
     * Category lists embed project counts (and project payloads embed category names),
     * so list keys follow both the Category and Project versions.
     */
    public static function key(string $base, ?string $present): string
    {
        return CacheKey::versioned(Category::class).':'.CacheKey::versioned(Project::class)
            .':'.$base.':loc:'.($present ?? 'none');
    }

    public static function flush(): void
    {
        CacheKey::bump(Category::class);
        CacheKey::bump(Project::class);
    }
}
