<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicTestimonialResource;
use App\Models\Testimonial;
use App\Support\CacheKey;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Approved testimonials for the marketing site (no auth).
 * Honors X-Content-Locale for translated quote / role / company and linked project title.
 */
class PublicTestimonialController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 24);
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $siteDefaultLocale = SiteLanguages::defaultCode();
        $localeKey = $present !== null && $present !== '' ? strtolower($present) : 'none';

        $cacheKey = CacheKey::versioned(Testimonial::class)
            .':public:testimonials:per:'.$perPage
            .':loc:'.$localeKey;

        $items = Cache::remember($cacheKey, 300, function () use ($perPage, $present, $siteDefaultLocale) {
            return Testimonial::query()
                ->with([
                    'translations',
                    'project' => function ($q) use ($present, $siteDefaultLocale) {
                        $q->select(['id', 'title', 'slug', 'content_locale', 'status'])
                            ->where('status', 'published')
                            ->with('translations');
                        if ($present) {
                            $q->where(function ($sub) use ($present, $siteDefaultLocale) {
                                $sub->where('content_locale', $present);
                                if ($present === $siteDefaultLocale) {
                                    $sub->orWhereNull('content_locale');
                                }
                                $sub->orWhereHas('translations', function ($tq) use ($present) {
                                    $tq->where('locale', $present);
                                });
                            });
                        }
                    },
                ])
                ->select([
                    'id', 'project_id', 'content_locale', 'client_name', 'client_role', 'company',
                    'rating', 'content', 'approved', 'created_at',
                ])
                ->where('approved', true)
                ->when($present, function ($query) use ($present, $siteDefaultLocale) {
                    $query->where(function ($q) use ($present, $siteDefaultLocale) {
                        $q->where('content_locale', $present);
                        if ($present === $siteDefaultLocale) {
                            $q->orWhereNull('content_locale');
                        }
                        $q->orWhereHas('translations', function ($tq) use ($present) {
                            $tq->where('locale', $present);
                        });
                    });
                })
                ->orderByDesc('created_at')
                ->limit($perPage)
                ->get();
        });

        if ($present) {
            $items->transform(function (Testimonial $t) use ($present) {
                TranslatableContentPresenter::applyTestimonial($t, $present);
                if ($t->relationLoaded('project') && $t->project !== null) {
                    TranslatableContentPresenter::applyProject($t->project, $present);
                }

                return $t;
            })->filter(function (Testimonial $t) use ($present) {
                if (! TranslatableContentPresenter::hasTestimonialContentForLocale($t, $present)) {
                    return false;
                }
                if ($t->relationLoaded('project') && $t->project !== null) {
                    return TranslatableContentPresenter::hasProjectContentForLocale($t->project, $present);
                }

                return true;
            })->values();
        }

        return PublicTestimonialResource::collection($items);
    }
}
