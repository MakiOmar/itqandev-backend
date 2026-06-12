<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Support\PublishedServicesQuery;
use App\Support\SeoMetaPresenter;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Published services for the marketing site (no auth).
 */
class PublicServiceController extends Controller
{
    public function index(Request $request)
    {
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $cacheKey = 'public:services:loc:' . ($present ?? 'none');

        $services = Cache::remember($cacheKey, 300, function () use ($present) {
            return PublishedServicesQuery::fetchPublished($present);
        });

        $payload = $services->map(function (Service $s) use ($present) {
            $primary = SiteLanguages::primaryLocaleForContent($s->content_locale);
            $picked = SeoMetaPresenter::pickLocalized($s->relationLoaded('seoMetas') ? $s->seoMetas : null, $present, $primary);

            return [
                'id' => (string) $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'shortDescription' => $s->short_description ?? '',
                'description' => $s->description ?? '',
                'process' => is_array($s->process) ? $s->process : [],
                'deliverables' => is_array($s->deliverables) ? $s->deliverables : [],
                'icon' => $s->icon,
                'seo_meta' => SeoMetaPresenter::toPublicSnippet($picked),
            ];
        })->values();

        return response()->json(['data' => $payload]);
    }
}
