<?php

namespace App\Support;

use App\Concerns\RefreshesCache;
use App\Models\Category;
use App\Models\Page;
use App\Services\ContentExport\CategoryListCacheInvalidator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Cached payloads embed their owner's media URLs (cover images, galleries, icons), so a media
 * upload/replace/delete invalidates the owner's caches like a save of the owner would.
 */
final class MediaOwnerCache
{
    public static function forget(Media $media): void
    {
        $type = (string) $media->model_type;
        $class = Relation::getMorphedModel($type) ?? $type;
        if ($class === '' || ! class_exists($class)) {
            return;
        }

        if ($class === Page::class) {
            Page::bumpPublicCacheVersion();

            return;
        }

        if (in_array(RefreshesCache::class, class_uses_recursive($class), true)) {
            CacheKey::bump($class);
        }

        if ($class === Category::class) {
            CategoryListCacheInvalidator::flush();
        }
    }
}
