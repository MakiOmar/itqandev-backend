<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Support\UniqueContentSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Sanitize, format, and uniquify content slugs before validation / persist.
 */
trait PreparesUniqueContentSlug
{
    /**
     * Merge a sanitized unique slug onto the request.
     *
     * @param  class-string<Model>  $modelClass
     * @param  string  $fallbackField  Used when slug is empty (e.g. title / name)
     * @param  bool  $onlyWhenPresent  When true (updates), skip if the request has no slug key
     */
    protected function mergeUniqueContentSlug(
        Request $request,
        string $modelClass,
        string $fallbackField = 'title',
        ?int $ignoreId = null,
        bool $onlyWhenPresent = false,
    ): void {
        if ($onlyWhenPresent && ! $request->exists('slug')) {
            return;
        }

        $source = trim((string) $request->input('slug', ''));
        if ($source === '') {
            $source = trim((string) $request->input($fallbackField, ''));
        }

        $request->merge([
            'slug' => UniqueContentSlug::fromSource($modelClass, $source, $ignoreId),
        ]);
    }
}
