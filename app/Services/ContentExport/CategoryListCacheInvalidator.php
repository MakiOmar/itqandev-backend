<?php

namespace App\Services\ContentExport;

use App\Support\SiteLanguages;
use Illuminate\Support\Facades\Cache;

final class CategoryListCacheInvalidator
{
    public const LIST_CACHE_KEY = 'categories:list:v3:json';

    public static function flush(): void
    {
        Cache::forget(self::LIST_CACHE_KEY);
        Cache::forget(self::LIST_CACHE_KEY.':loc:none');
        foreach (SiteLanguages::all() as $row) {
            $code = is_array($row) && isset($row['code']) ? (string) $row['code'] : '';
            if ($code !== '') {
                Cache::forget(self::LIST_CACHE_KEY.':loc:'.strtolower($code));
            }
        }
    }
}
