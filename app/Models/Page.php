<?php

namespace App\Models;

use App\Casts\JsonWithAppMediaUrls;
use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use App\Support\CacheKey;
use App\Support\StaticHomepage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    use HasFactory, InvalidatesCache, RefreshesCache;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'parent_id',
        'slug',
        'content_locale',
        'status',
        'exclude_from_search',
        'published_at',
        'title',
        'subtitle',
        'excerpt',
        'sections',
        'header_layout_id',
        'footer_layout_id',
    ];

    public const PUBLIC_CACHE_VERSION_KEY = 'pages:cache_version';

    protected static function booted(): void
    {
        // Admin list caches are versioned separately via RefreshesCache (every save).
        static::saved(function (self $page) {
            StaticHomepage::forgetPage((int) $page->id);
            if ($page->affectsPublicPages()) {
                CacheKey::incrementVersion(self::PUBLIC_CACHE_VERSION_KEY);
            }
        });
        static::deleted(function (self $page) {
            StaticHomepage::forgetPage((int) $page->id);
            if ($page->affectsPublicPages()) {
                CacheKey::incrementVersion(self::PUBLIC_CACHE_VERSION_KEY);
            }
        });
    }

    public static function publicCacheVersion(): int
    {
        return (int) Cache::get(self::PUBLIC_CACHE_VERSION_KEY, 1);
    }

    /**
     * Invalidates public and admin page payloads; for writes that bypass model events
     * (mass deletes, translation syncs, settings and imports).
     */
    public static function bumpPublicCacheVersion(): void
    {
        CacheKey::incrementVersion(self::PUBLIC_CACHE_VERSION_KEY);
        CacheKey::bump(self::class);
    }

    /**
     * Drafts that stay drafts never reach public payloads.
     */
    public function affectsPublicPages(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            || $this->getOriginal('status') === self::STATUS_PUBLISHED;
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'sections' => JsonWithAppMediaUrls::class,
            'exclude_from_search' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('title');
    }

    public function translations()
    {
        return $this->hasMany(PageTranslation::class);
    }

    public function seoMetas()
    {
        return $this->morphMany(SeoMeta::class, 'seoable');
    }

    public function headerLayout()
    {
        return $this->belongsTo(ChromeLayout::class, 'header_layout_id');
    }

    public function footerLayout()
    {
        return $this->belongsTo(ChromeLayout::class, 'footer_layout_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
