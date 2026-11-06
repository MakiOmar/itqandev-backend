<?php

namespace App\Models;

use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
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
        'layout',
        'actions',
        'settings',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::bumpPublicCacheVersion());
        static::deleted(fn () => self::bumpPublicCacheVersion());
    }

    public static function bumpPublicCacheVersion(): void
    {
        CacheKey::incrementVersion('forms:cache_version');
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'layout' => 'array',
            'actions' => 'array',
            'settings' => 'array',
        ];
    }

    public function translations()
    {
        return $this->hasMany(FormTranslation::class);
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
