<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
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
            $list = Service::query()
                ->with('translations')
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
            if ($present) {
                $list->each(function (Service $service) use ($present) {
                    TranslatableContentPresenter::applyService($service, $present);
                });
            }

            return $list;
        });

        $payload = $services->map(function (Service $s) {
            return [
                'id' => (string) $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'shortDescription' => $s->short_description ?? '',
                'description' => $s->description ?? '',
                'process' => is_array($s->process) ? $s->process : [],
                'deliverables' => is_array($s->deliverables) ? $s->deliverables : [],
                'icon' => $s->icon,
            ];
        })->values();

        return response()->json(['data' => $payload]);
    }
}
