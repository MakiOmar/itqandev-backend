<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolve a unique slug for a content model (WordPress-style: append -2, -3, …).
 */
final class UniqueContentSlug
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function suggest(string $modelClass, string $base, ?int $ignoreId = null): string
    {
        return self::suggestFromQuery($modelClass::query(), $base, $ignoreId);
    }

    /**
     * Uniquify against an arbitrary query (e.g. chrome_layouts scoped by kind).
     *
     * @param  Builder<Model>  $query
     */
    public static function suggestFromQuery(Builder $query, string $base, ?int $ignoreId = null): string
    {
        $base = trim($base);
        if ($base === '') {
            return '';
        }

        if (! self::slugTakenOnQuery($query, $base, $ignoreId)) {
            return $base;
        }

        for ($i = 2; $i <= 10_000; $i++) {
            $candidate = $base.'-'.$i;
            if (! self::slugTakenOnQuery($query, $candidate, $ignoreId)) {
                return $candidate;
            }
        }

        return $base.'-'.uniqid();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private static function slugTakenOnQuery(Builder $query, string $slug, ?int $ignoreId): bool
    {
        return (clone $query)
            ->where('slug', $slug)
            ->when($ignoreId !== null, function (Builder $q) use ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            })
            ->exists();
    }
}
