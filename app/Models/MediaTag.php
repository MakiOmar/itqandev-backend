<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class MediaTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(
            \Spatie\MediaLibrary\MediaCollections\Models\Media::class,
            'media_media_tag',
            'media_tag_id',
            'media_id'
        );
    }
}
