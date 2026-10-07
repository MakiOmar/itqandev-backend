<?php

namespace App\Models;

use App\Casts\JsonWithAppMediaUrls;
use Illuminate\Database\Eloquent\Model;

class BuilderGlobal extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'document',
    ];

    protected function casts(): array
    {
        return [
            'document' => JsonWithAppMediaUrls::class,
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
