<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProjectCardResource;
use App\Models\Project;
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
        ]);

        $perPage = (int) ($filters['per_page'] ?? 12);

        $query = Project::query()
            ->with([
                'categories:id,name',
                'skills:id,name',
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

        if (! empty($filters['featured'])) {
            $query->where('featured', true);
        }

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $page = (int) $request->get('page', 1);
        $cacheKey = 'public:projects:'.md5(json_encode([$filters, $page, $perPage])).':loc:'.($present ?? 'none');

        $paginator = Cache::remember($cacheKey, 300, function () use ($query, $perPage) {
            return $query->paginate($perPage);
        });

        if ($present) {
            $paginator->getCollection()->transform(function (Project $project) use ($present) {
                TranslatableContentPresenter::applyProject($project, $present);

                return $project;
            });
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
                'categories:id,name',
                'skills:id,name',
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
