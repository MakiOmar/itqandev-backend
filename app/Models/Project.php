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
        'gallery_media_ids',
        'status',
        'link_url',
        'repo_url',
        'demo_url',
        'featured',
        'published_at',
        'header_layout_id',
        'footer_layout_id',
    ];

    public const GALLERY_MAX = 100;

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
        'gallery_media_ids' => 'array',
    ];

    /** @return list<int> Gallery media ids in display order. */
    public function galleryMediaIds(): array
    {
        $ids = array_map('intval', is_array($this->gallery_media_ids) ? $this->gallery_media_ids : []);

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

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
