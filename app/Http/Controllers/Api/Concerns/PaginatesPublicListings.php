<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Support\CacheKey;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Shared pieces of cached public listings: bounded paging input, the strict locale overlay,
 * and serialization to a plain array before it is cached.
 */
trait PaginatesPublicListings
{
    /**
     * @return array<string, list<string>>
     */
    protected function publicPagingRules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @param  Closure(Model, string): void  $apply  Locale overlay (e.g. TranslatableContentPresenter::applyProject).
     * @param  Closure(Model, string): bool  $hasContent  Strict no-fallback check for the locale.
     * @param  class-string<JsonResource>  $resource
     * @return array<string, mixed>
     */
    protected function presentPublicPage(
        Request $request,
        LengthAwarePaginator $paginator,
        ?string $present,
        Closure $apply,
        Closure $hasContent,
        string $resource,
    ): array {
        if ($present) {
            $paginator->setCollection(
                $paginator->getCollection()
                    ->each(fn (Model $model) => $apply($model, $present))
                    ->filter(fn (Model $model) => $hasContent($model, $present))
                    ->values()
            );
        }

        return $resource::collection($paginator)->response($request)->getData(true);
    }

    /**
     * Whether a filter slug exists, from a per-version cached set. Lets callers skip caching
     * results for made-up slugs (otherwise each random value writes a cache entry).
     *
     * @param  class-string<Model>  $modelClass  Must bump its CacheKey version on save (RefreshesCache).
     */
    protected function publicSlugExists(string $modelClass, string $slug): bool
    {
        $slugs = Cache::remember(
            CacheKey::versioned($modelClass).':public:slugs',
            3600,
            fn () => $modelClass::query()
                ->pluck('slug')
                ->mapWithKeys(fn ($value) => [mb_strtolower((string) $value) => true])
                ->all(),
        );

        return isset($slugs[mb_strtolower($slug)]);
    }
}
