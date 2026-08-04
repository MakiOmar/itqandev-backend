<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaTranslation extends Model
{
    protected $fillable = [
        'media_id',
        'locale',
        'alt_text',
        'description',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(AppMedia::class, 'media_id');
    }
}
