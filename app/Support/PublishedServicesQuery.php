<?php

namespace App\Support;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublishedServicesQuery
{
    public static function base(?string $present): Builder
    {
        $query = Service::query()
            ->with(['translations', 'seoMetas'])
            ->where('is_published', true);

        if ($present !== null && $present !== '') {
            TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present);
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
}
