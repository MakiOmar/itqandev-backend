<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Services\PublicMarketingShellService;
use Illuminate\Database\Eloquent\Model;

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

        if ($model instanceof BlogPost || $model instanceof Project) {
            CacheKey::bump($model::class);

            return;
        }

        if ($model instanceof Service) {
            CacheKey::bump(Service::class);
            PublicMarketingShellService::forgetShellCaches();
        }
    }
}
