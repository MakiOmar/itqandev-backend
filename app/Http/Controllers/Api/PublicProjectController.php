<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProjectCardResource;
use App\Models\Project;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Unauthenticated read-only project listing for the marketing site.
 */
class PublicProjectController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'featured' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'category_slug' => ['nullable', 'string', 'max:255'],
            'skill_slug' => ['nullable', 'string', 'max:255'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 12);
        $page = (int) $request->get('page', 1);
        $categorySlug = isset($filters['category_slug']) ? trim((string) $filters['category_slug']) : '';
        $skillSlug = isset($filters['skill_slug']) ? trim((string) $filters['skill_slug']) : '';

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $cacheKey = 'public:projects:'.md5(json_encode([$filters, $page, $perPage])).':loc:'.($present ?? 'none');

        $siteDefaultLocale = SiteLanguages::defaultCode();
        $paginator = Cache::remember($cacheKey, 300, function () use ($filters, $perPage, $page, $categorySlug, $skillSlug, $present, $siteDefaultLocale) {
            $query = Project::query()
                ->with([
                    'categories:id,name,slug',
                    'skills:id,name,slug',
                    'translations',
                    'media' => function ($q) {
                        $q->where('collection_name', 'hero');
                    },
                ])
                ->select([
                    'id', 'title', 'slug', 'content_locale', 'status', 'featured',
                    'published_at', 'summary', 'description',
                ])
                ->where('status', 'published')
                ->latest('published_at');

            if ($present) {
                $query->where(function ($q) use ($present, $siteDefaultLocale) {
                    $q->where('content_locale', $present);
                    if ($present === $siteDefaultLocale) {
                        $q->orWhereNull('content_locale');
                    }
                    $q->orWhereHas('translations', function ($tq) use ($present) {
                        $tq->where('locale', $present);
                    });
                });
            }

            if (! empty($filters['featured'])) {
                $query->where('featured', true);
            }

            if ($categorySlug !== '') {
                $query->whereHas('categories', function ($q) use ($categorySlug) {
                    $q->where('slug', $categorySlug);
                });
            }

            if ($skillSlug !== '') {
                $query->whereHas('skills', function ($q) use ($skillSlug) {
                    $q->where('slug', $skillSlug);
                });
            }

            return $query->paginate($perPage, ['*'], 'page', $page);
        });

        if ($present) {
            $paginator->getCollection()->transform(function (Project $project) use ($present) {
                TranslatableContentPresenter::applyProject($project, $present);

                return $project;
            })->filter(function (Project $project) use ($present) {
                return TranslatableContentPresenter::hasProjectContentForLocale($project, $present);
            })->values();
        }

        return PublicProjectCardResource::collection($paginator);
    }

    /**
     * Single published project by slug (localized fields via X-Content-Locale).
     */
    public function show(string $slug)
    {
        $project = Project::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with([
                'categories:id,name,slug',
                'skills:id,name,slug',
                'translations',
                'media' => function ($q) {
                    $q->where('collection_name', 'hero');
                },
            ])
            ->select([
                'id', 'title', 'slug', 'content_locale', 'status', 'featured',
                'published_at', 'summary', 'description',
            ])
            ->firstOrFail();

        $present = TranslatableContentPresenter::requestedPresentationLocale(request());
        if ($present) {
            TranslatableContentPresenter::applyProject($project, $present);
        }

        return new PublicProjectCardResource($project);
    }
}
