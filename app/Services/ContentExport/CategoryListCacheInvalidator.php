<?php

namespace App\Services\ContentExport;


final class CategoryListCacheInvalidator
{
    public const LIST_CACHE_KEY = 'categories:list:v3:json';

    public const PUBLIC_LIST_CACHE_KEY = 'public:categories:v1';

    public static function flush(): void
    {
        TranslatableListCacheInvalidator::flushPrefix(self::LIST_CACHE_KEY);
        TranslatableListCacheInvalidator::flushPrefix(self::PUBLIC_LIST_CACHE_KEY);
    }
}
