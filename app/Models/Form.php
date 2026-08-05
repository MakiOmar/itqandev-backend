<?php

namespace App\Models;

use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
        $version = (int) Cache::get('forms:cache_version', 1);
        Cache::forever('forms:cache_version', $version + 1);
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
