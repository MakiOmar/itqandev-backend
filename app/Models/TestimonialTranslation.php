<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestimonialTranslation extends Model
{
    protected $fillable = [
        'testimonial_id',
        'locale',
        'content',
        'client_role',
        'company',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            CacheKey::bump(Testimonial::class);
        });

        static::deleted(function () {
            CacheKey::bump(Testimonial::class);
        });
    }

    public function testimonial(): BelongsTo
    {
        return $this->belongsTo(Testimonial::class);
    }
}
