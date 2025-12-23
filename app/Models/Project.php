<?php

namespace App\Models;

use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RefreshesCache;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'status',
        'link_url',
        'repo_url',
        'demo_url',
        'featured',
        'published_at',
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

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')->singleFile();
        $this->addMediaCollection('gallery')->useDisk('public');
        $this->addMediaCollection('attachments')->useDisk('public');
        $this->addMediaCollection('video')->singleFile();
    }
}
