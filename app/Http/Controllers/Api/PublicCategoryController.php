<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicCategoryResource;
use App\Models\Category;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Unauthenticated category list for marketing portfolio filters.
 */
class PublicCategoryController extends Controller
{
    public function index(Request $request)
    {
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $cacheKey = 'public:categories:v1:loc:'.($present ?? 'none');

        $payload = Cache::remember($cacheKey, 300, function () use ($present) {
            $query = Category::query()
                ->with(['translations'])
                ->withCount([
                    'projects as published_projects_count' => function ($q) {
                        $q->where('status', 'published');
                    },
                ])
                ->whereHas('projects', function ($q) {
                    $q->where('status', 'published');
                })
                ->orderBy('name');

            if ($present) {
                TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present);
            }

            $categories = $query->get();

            if ($present) {
                $categories = $categories
                    ->map(function (Category $category) use ($present) {
                        TranslatableContentPresenter::applyCategory($category, $present);

                        return $category;
                    })
                    ->filter(function (Category $category) use ($present) {
                        return TranslatableContentPresenter::hasCategoryContentForLocale($category, $present);
                    })
                    ->values();
            }

            return PublicCategoryResource::collection($categories)->resolve();
        });

        return response()->json(['data' => $payload]);
    }
}
