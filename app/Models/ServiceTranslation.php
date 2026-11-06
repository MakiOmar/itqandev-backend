<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'locale',
        'name',
        'short_description',
        'description',
        'process',
        'deliverables',
    ];

    protected function casts(): array
    {
        return [
            'process' => 'array',
            'deliverables' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => CacheKey::bump(Service::class));
        static::deleted(fn () => CacheKey::bump(Service::class));
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
