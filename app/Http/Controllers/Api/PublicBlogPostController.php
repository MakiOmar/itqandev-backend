<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBlogPostResource;
use App\Models\BlogPost;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicBlogPostController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 12);
        $page = (int) $request->get('page', 1);
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $cacheKey = 'public:blog-posts:'.md5(json_encode([$perPage, $page])).':loc:'.($present ?? 'none');

        $siteDefaultLocale = SiteLanguages::defaultCode();
        $paginator = Cache::remember($cacheKey, 300, function () use ($perPage, $page, $present, $siteDefaultLocale) {
            $query = BlogPost::query()
                ->with(['author:id,name', 'translations', 'seoMetas'])
                ->where('status', 'published')
                ->orderByDesc('published_at');

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

            return $query->paginate($perPage, ['*'], 'page', $page);
        });

        if ($present) {
            $paginator->getCollection()->transform(function (BlogPost $post) use ($present) {
                TranslatableContentPresenter::applyBlogPost($post, $present);

                return $post;
            })->filter(function (BlogPost $post) use ($present) {
                return TranslatableContentPresenter::hasBlogPostContentForLocale($post, $present);
            })->values();
        }

        return PublicBlogPostResource::collection($paginator);
    }

    public function show(Request $request, string $slug)
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['author:id,name', 'translations', 'seoMetas'])
            ->first();

        if (! $post instanceof BlogPost) {
            abort(404);
        }

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        if ($present) {
            TranslatableContentPresenter::applyBlogPost($post, $present);
            if (! TranslatableContentPresenter::hasBlogPostContentForLocale($post, $present)) {
                abort(404);
            }
        }

        return new PublicBlogPostResource($post);
    }
}
