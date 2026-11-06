<?php

namespace App\Models;

use App\Casts\JsonWithAppMediaUrls;
use App\Concerns\RefreshesCache;
use App\Services\Appearance\GlobalWidgetService;
use App\Support\RequestMemo;
use Illuminate\Database\Eloquent\Model;

class BuilderGlobal extends Model
{
    use RefreshesCache;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'document',
    ];

    protected static function booted(): void
    {
        $forgetMemo = fn (self $global) => app(RequestMemo::class)->forget(GlobalWidgetService::memoKey((int) $global->id));
        static::saved($forgetMemo);
        static::deleted($forgetMemo);
    }

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
