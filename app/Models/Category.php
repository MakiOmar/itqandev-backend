<?php

namespace App\Models;

use App\Concerns\RefreshesCache;
use App\Services\ContentExport\CategoryListCacheInvalidator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Category extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use RefreshesCache;

    protected static function booted(): void
    {
        static::saved(function () {
            CategoryListCacheInvalidator::flush();
        });

        static::deleted(function () {
            CategoryListCacheInvalidator::flush();
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'content_locale',
        'description',
        'is_featured',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }

    public function seoMetas()
    {
        return $this->morphMany(SeoMeta::class, 'seoable');
    }

    public function translations()
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')->singleFile();
        $this->addMediaCollection('thumb')->singleFile();
        $this->addMediaCollection('banner')->singleFile();
    }
}
