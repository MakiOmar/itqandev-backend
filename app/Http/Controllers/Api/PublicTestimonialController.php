<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicTestimonialResource;
use App\Models\Testimonial;
use App\Support\CacheKey;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Approved testimonials for the marketing site (no auth).
 * Honors X-Content-Locale for translated quote / role / company and linked project title.
 */
class PublicTestimonialController extends Controller
{
    /** Translation columns that make a testimonial count as written in a locale. */
    private const CONTENT_FIELDS = ['content', 'client_role', 'company'];

    private const PROJECT_CONTENT_FIELDS = ['title', 'summary', 'description'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 24);
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);

        $cacheKey = CacheKey::versioned(Testimonial::class)
            .':public:testimonials:per:'.$perPage
            .':loc:'.($present ?? 'none');

        $payload = Cache::remember($cacheKey, 300, function () use ($request, $perPage, $present) {
            $query = Testimonial::query()
                ->with([
                    'translations',
                    'project' => function ($q) use ($present) {
                        $q->select(['id', 'title', 'slug', 'content_locale', 'status'])
                            ->where('status', 'published')
                            ->with('translations');
                        TranslatableContentPresenter::scopeQueryForPresentationLocale($q->getQuery(), $present, self::PROJECT_CONTENT_FIELDS);
                    },
                ])
                ->select([
                    'id', 'project_id', 'content_locale', 'client_name', 'client_role', 'company',
                    'rating', 'content', 'approved', 'created_at',
                ])
                ->where('approved', true)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($perPage);

            TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present, self::CONTENT_FIELDS);

            $items = $query->get();
            if ($present) {
                $items = $items
                    ->each(fn (Testimonial $t) => $this->localize($t, $present))
                    ->filter(fn (Testimonial $t) => TranslatableContentPresenter::hasTestimonialContentForLocale($t, $present))
                    ->values();
            }

            return PublicTestimonialResource::collection($items)->response($request)->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * Applies the locale overlay; a linked project without content in the locale is dropped
     * from the testimonial (no primary-language fallback) rather than hiding the testimonial.
     */
    private function localize(Testimonial $testimonial, string $present): void
    {
        TranslatableContentPresenter::applyTestimonial($testimonial, $present);

        $project = $testimonial->relationLoaded('project') ? $testimonial->project : null;
        if ($project === null) {
            return;
        }
        TranslatableContentPresenter::applyProject($project, $present);
        if (! TranslatableContentPresenter::hasProjectContentForLocale($project, $present)) {
            $testimonial->setRelation('project', null);
        }
    }
}
