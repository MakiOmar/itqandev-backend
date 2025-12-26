<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MediaFolder extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'description',
        'order',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($folder) {
            if (empty($folder->slug)) {
                $folder->slug = Str::slug($folder->name);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id')->orderBy('order');
    }

    public function media(): HasMany
    {
        return $this->hasMany(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'folder_id');
    }
}
