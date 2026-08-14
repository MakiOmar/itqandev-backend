<?php

namespace App\Models;

use App\Concerns\InvalidatesCache;
use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, InvalidatesCache, RefreshesCache;

    protected $fillable = [
        'title',
        'slug',
        'content_locale',
        'summary',
        'description',
        'status',
        'link_url',
        'repo_url',
        'demo_url',
        'featured',
        'published_at',
        'header_layout_id',
        'footer_layout_id',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class);
    }

    public function testimonials()
    {
        return $this->hasMany(Testimonial::class);
    }

    public function seoMetas()
    {
        return $this->morphMany(SeoMeta::class, 'seoable');
    }

    public function translations()
    {
        return $this->hasMany(ProjectTranslation::class);
    }

    public function headerLayout()
    {
        return $this->belongsTo(ChromeLayout::class, 'header_layout_id');
    }

    public function footerLayout()
    {
        return $this->belongsTo(ChromeLayout::class, 'footer_layout_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')->singleFile();
        $this->addMediaCollection('gallery')->useDisk('public');
        $this->addMediaCollection('attachments')->useDisk('public');
        $this->addMediaCollection('video')->singleFile();
    }
}
