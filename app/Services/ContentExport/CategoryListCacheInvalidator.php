<?php

namespace App\Services\ContentExport;


final class CategoryListCacheInvalidator
{
    public const LIST_CACHE_KEY = 'categories:list:v3:json';

    public static function flush(): void
    {
        TranslatableListCacheInvalidator::flushPrefix(self::LIST_CACHE_KEY);
    }
}
