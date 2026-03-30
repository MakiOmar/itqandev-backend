<?php

namespace App\Models;

use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Category extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use RefreshesCache;
    use InvalidatesCache;

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

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
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
