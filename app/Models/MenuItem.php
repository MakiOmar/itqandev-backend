<?php

namespace App\Models;

use App\Services\PublicMenuResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    public const TYPE_CUSTOM_LINK = 'custom_link';

    public const TYPE_STATIC_ROUTE = 'static_route';

    public const TYPE_PROJECT = 'project';

    public const TYPE_BLOG_POST = 'blog_post';

    public const TYPE_SERVICE = 'service';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_SKILL = 'skill';

    public const TYPE_PAGE = 'page';

    /** @var list<string> */
    public const ITEM_TYPES = [
        self::TYPE_CUSTOM_LINK,
        self::TYPE_STATIC_ROUTE,
        self::TYPE_PROJECT,
        self::TYPE_BLOG_POST,
        self::TYPE_SERVICE,
        self::TYPE_CATEGORY,
        self::TYPE_SKILL,
        self::TYPE_PAGE,
    ];

    protected $fillable = [
        'menu_id',
        'parent_id',
        'sort_order',
        'label',
        'item_type',
        'url',
        'static_route_key',
        'reference_id',
        'open_in_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'reference_id' => 'integer',
            'open_in_new_tab' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    protected static function booted(): void
    {
        static::saved(function (MenuItem $item): void {
            $item->loadMissing('menu');
            if ($item->menu) {
                PublicMenuResolver::forgetCacheForMenu($item->menu);
            }
        });

        static::deleted(function (MenuItem $item): void {
            $item->loadMissing('menu');
            if ($item->menu) {
                PublicMenuResolver::forgetCacheForMenu($item->menu);
            }
        });
    }
}
