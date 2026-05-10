<?php

namespace App\Models;

use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Skill extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RefreshesCache, InvalidatesCache;

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
