<?php

namespace App\Models;

use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Skill extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RefreshesCache, InvalidatesCache;

    protected static function booted(): void
    {
        // Project payloads embed skill names as tags.
        static::saved(fn () => CacheKey::bump(Project::class));
        static::deleted(fn () => CacheKey::bump(Project::class));
    }

    protected $fillable = [
        'name',
        'slug',
        'content_locale',
        'description',
        'icon_hint',
    ];

    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }

    public function translations()
    {
        return $this->hasMany(SkillTranslation::class);
    }

    public function seoMetas()
    {
        return $this->morphMany(SeoMeta::class, 'seoable');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')->singleFile();
    }
}
