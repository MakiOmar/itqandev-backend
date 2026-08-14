<?php

namespace App\Models;

use App\Concerns\RefreshesCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BlogPost extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RefreshesCache;

    protected $fillable = [
        'title',
        'slug',
        'content_locale',
        'excerpt',
        'content',
        'status',
        'featured',
        'author_id',
        'published_at',
        'header_layout_id',
        'footer_layout_id',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function seoMetas(): MorphMany
    {
        return $this->morphMany(SeoMeta::class, 'seoable');
    }

    public function translations()
    {
        return $this->hasMany(BlogPostTranslation::class);
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
        $this->addMediaCollection('featured_image')->singleFile();
        $this->addMediaCollection('gallery')->useDisk('public');
    }
}
