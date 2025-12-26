<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class AppMedia extends SpatieMedia
{
    /**
     * Get the folder that owns the media.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * Get the tags for the media.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            MediaTag::class,
            'media_media_tag',
            'media_id',
            'media_tag_id'
        );
    }

    /**
     * Get the usages for the media.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class, 'media_id');
    }
}

