<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPostTranslation extends Model
{
    protected $fillable = [
        'blog_post_id',
        'locale',
        'title',
        'excerpt',
        'content',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $t) {
            CacheKey::bump(BlogPost::class);
        });
        static::deleted(function (self $t) {
            CacheKey::bump(BlogPost::class);
        });
    }

    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }
}
