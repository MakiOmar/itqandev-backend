<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChromeLayout extends Model
{
    public const KIND_HEADER = 'header';

    public const KIND_FOOTER = 'footer';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

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
            'document' => 'array',
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
