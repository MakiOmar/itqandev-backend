<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Service;
use App\Services\PublicMarketingShellService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Drops cached public payloads that embed a record's `seo_meta`, so an SEO edit shows on the
 * next request instead of after the cache TTL.
 */
final class PublicSeoCache
{
    public static function forget(Model $model): void
    {
        if ($model instanceof Page) {
            Page::bumpPublicCacheVersion();

            return;
        }

        if ($model instanceof BlogPost) {
            CacheKey::bump(BlogPost::class);

            return;
        }

        if ($model instanceof Service) {
            Cache::forget('public:services:loc:none');
            foreach (SiteLanguages::all() as $row) {
                $code = strtolower(trim((string) ($row['code'] ?? '')));
                if ($code !== '') {
                    Cache::forget('public:services:loc:'.$code);
                }
            }
            PublicMarketingShellService::forgetShellCaches();
        }
    }
}
