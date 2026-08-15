<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeTemplate extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'name',
        'status',
        'conditions',
        'header_layout_id',
        'footer_layout_id',
        'body_layout_id',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'header_layout_id' => 'integer',
            'footer_layout_id' => 'integer',
            'body_layout_id' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function headerLayout(): BelongsTo
    {
        return $this->belongsTo(ChromeLayout::class, 'header_layout_id');
    }

    public function footerLayout(): BelongsTo
    {
        return $this->belongsTo(ChromeLayout::class, 'footer_layout_id');
    }

    public function bodyLayout(): BelongsTo
    {
        return $this->belongsTo(ChromeLayout::class, 'body_layout_id');
    }
}
