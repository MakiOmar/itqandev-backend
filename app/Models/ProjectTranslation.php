<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTranslation extends Model
{
    protected $fillable = [
        'project_id',
        'locale',
        'title',
        'summary',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $t) {
            CacheKey::bump(Project::class);
        });
        static::deleted(function (self $t) {
            CacheKey::bump(Project::class);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
