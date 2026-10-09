<?php

namespace App\Models;

use App\Casts\JsonWithAppMediaUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChromeLayout extends Model
{
    public const KIND_HEADER = 'header';

    public const KIND_FOOTER = 'footer';

    public const KIND_BODY = 'body';

    public const KIND_SINGLE = 'single';

    public const KIND_ARCHIVE = 'archive';

    public const KIND_LOOP_ITEM = 'loop_item';

    public const KIND_OVERLAY = 'overlay';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_HEADER,
        self::KIND_FOOTER,
        self::KIND_BODY,
        self::KIND_SINGLE,
        self::KIND_ARCHIVE,
        self::KIND_LOOP_ITEM,
        self::KIND_OVERLAY,
    ];

    /** API path segment (e.g. `/appearance/loop-items`) for each kind. */
    public const ROUTE_SEGMENTS = [
        self::KIND_HEADER => 'headers',
        self::KIND_FOOTER => 'footers',
        self::KIND_BODY => 'bodies',
        self::KIND_SINGLE => 'singles',
        self::KIND_ARCHIVE => 'archives',
        self::KIND_LOOP_ITEM => 'loop-items',
        self::KIND_OVERLAY => 'overlays',
    ];

    /**
     * Accepts a kind (`loop_item`) or its route segment (`loop-items`).
     */
    public static function kindFromRouteSegment(string $segment): ?string
    {
        if (in_array($segment, self::KINDS, true)) {
            return $segment;
        }
        $kind = array_search($segment, self::ROUTE_SEGMENTS, true);

        return is_string($kind) ? $kind : null;
    }

    protected $fillable = [
        'kind',
        'name',
        'slug',
        'status',
        'document',
        'is_site_default',
    ];

    protected function casts(): array
    {
        return [
            'document' => JsonWithAppMediaUrls::class,
            'is_site_default' => 'boolean',
        ];
    }

    public function scopeKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeSiteDefault(Builder $query): Builder
    {
        return $query->where('is_site_default', true);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function pagesUsingHeader(): HasMany
    {
        return $this->hasMany(Page::class, 'header_layout_id');
    }

    public function pagesUsingFooter(): HasMany
    {
        return $this->hasMany(Page::class, 'footer_layout_id');
    }

    public function projectsUsingHeader(): HasMany
    {
        return $this->hasMany(Project::class, 'header_layout_id');
    }

    public function projectsUsingFooter(): HasMany
    {
        return $this->hasMany(Project::class, 'footer_layout_id');
    }

    public function blogPostsUsingHeader(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'header_layout_id');
    }

    public function blogPostsUsingFooter(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'footer_layout_id');
    }

    public function servicesUsingHeader(): HasMany
    {
        return $this->hasMany(Service::class, 'header_layout_id');
    }

    public function servicesUsingFooter(): HasMany
    {
        return $this->hasMany(Service::class, 'footer_layout_id');
    }
}
