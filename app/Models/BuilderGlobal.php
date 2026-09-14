<?php

namespace App\Models;

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
            'document' => 'array',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
