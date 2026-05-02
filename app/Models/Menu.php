<?php

namespace App\Models;

use App\Services\PublicMenuResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    protected static function booted(): void
    {
        static::saved(function (Menu $menu): void {
            PublicMenuResolver::forgetCacheForMenu($menu);
        });

        static::deleted(function (Menu $menu): void {
            PublicMenuResolver::forgetCacheForMenu($menu);
        });
    }
}
