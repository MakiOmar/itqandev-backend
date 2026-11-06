<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\BuilderGlobal;
use App\Models\Category;
use App\Models\Form;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Testimonial;

/**
 * Presented builder documents (pages, chrome layouts, the shell) inline records through loop
 * grids, dynamic tags, forms and globals, so their cache keys follow these versions. Pages use
 * the published-only counter so draft saves keep public caches warm.
 */
final class BuilderContentVersions
{
    public const MODELS = [
        Project::class,
        BlogPost::class,
        Service::class,
        Testimonial::class,
        Category::class,
        Skill::class,
        Form::class,
        BuilderGlobal::class,
    ];

    /**
     * @param  list<string>  $extraKeys  further `CacheKey::bump()` keys the payload depends on
     */
    public static function token(array $extraKeys = []): string
    {
        return CacheKey::versionedMany([...$extraKeys, ...self::MODELS], [Page::PUBLIC_CACHE_VERSION_KEY]);
    }
}
