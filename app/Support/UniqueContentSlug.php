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
        $base = trim($base);
        if ($base === '') {
            return '';
        }

        if (! self::slugTaken($modelClass, $base, $ignoreId)) {
            return $base;
        }

        for ($i = 2; $i <= 10_000; $i++) {
            $candidate = $base.'-'.$i;
            if (! self::slugTaken($modelClass, $candidate, $ignoreId)) {
                return $candidate;
            }
        }

        return $base.'-'.uniqid();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function slugTaken(string $modelClass, string $slug, ?int $ignoreId): bool
    {
        return $modelClass::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, function (Builder $q) use ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            })
            ->exists();
    }
}
