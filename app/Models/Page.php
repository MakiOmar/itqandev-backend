<?php

namespace App\Models;

use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    use HasFactory, InvalidatesCache, RefreshesCache;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'slug',
        'content_locale',
        'status',
        'published_at',
        'title',
        'excerpt',
        'sections',
        'header_layout_id',
        'footer_layout_id',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::bumpPublicCacheVersion());
        static::deleted(fn () => self::bumpPublicCacheVersion());
    }

    public static function bumpPublicCacheVersion(): void
    {
        $version = (int) Cache::get('pages:cache_version', 1);
        Cache::forever('pages:cache_version', $version + 1);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'sections' => 'array',
        ];
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
