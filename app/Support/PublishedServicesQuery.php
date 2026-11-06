<?php

namespace App\Support;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PublishedServicesQuery
{
    /** Translation columns that make a service count as written in a locale. */
    public const CONTENT_FIELDS = ['name', 'short_description', 'description', 'process', 'deliverables'];

    private const CACHE_SECONDS = 300;

    public static function base(?string $present): Builder
    {
        $query = Service::query()
            ->with(['translations', 'seoMetas'])
            ->where('is_published', true);

        if ($present !== null && $present !== '') {
            TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present, self::CONTENT_FIELDS);
        }

        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return Collection<int, Service>
     */
    public static function fetchPublished(?string $present): Collection
    {
        $list = self::base($present)->get();

        if ($present === null || $present === '') {
            return $list;
        }

        return $list
            ->each(function (Service $service) use ($present) {
                TranslatableContentPresenter::applyService($service, $present);
            })
            ->filter(function (Service $service) use ($present) {
                return TranslatableContentPresenter::hasServiceContentForLocale($service, $present);
            })
            ->values();
    }

    /**
     * Public service cards shared by `GET /api/public/services` and the marketing shell.
     *
     * @return list<array<string, mixed>>
     */
    public static function publicPayload(?string $present): array
    {
        $present = $present !== null && $present !== '' ? $present : null;
        $cacheKey = CacheKey::versioned(Service::class).':public:list:loc:'.($present ?? 'none');

        return Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($present) {
            return self::fetchPublished($present)
                ->map(fn (Service $service) => self::card($service, $present))
                ->values()
                ->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function card(Service $service, ?string $present): array
    {
        $primary = SiteLanguages::primaryLocaleForContent($service->content_locale);
        $picked = SeoMetaPresenter::pickLocalized(
            $service->relationLoaded('seoMetas') ? $service->seoMetas : null,
            $present,
            $primary
        );

        return [
            'id' => (string) $service->id,
            'slug' => $service->slug,
            'name' => $service->name,
            'shortDescription' => $service->short_description ?? '',
            'description' => $service->description ?? '',
            'process' => is_array($service->process) ? $service->process : [],
            'deliverables' => is_array($service->deliverables) ? $service->deliverables : [],
            'icon' => $service->icon,
            'seo_meta' => SeoMetaPresenter::toPublicSnippet($picked),
        ];
    }
}
