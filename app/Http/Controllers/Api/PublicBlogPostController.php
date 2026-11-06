<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBlogPostResource;
use App\Models\BlogPost;
use App\Support\CacheKey;
use App\Support\PublicCache;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicBlogPostController extends Controller
{
    private const MAX_PAGE = 1000;

    private const CACHE_TTL_SECONDS = 300;

    /** Translation columns that make a post count as written in a locale. */
    private const CONTENT_FIELDS = ['title', 'excerpt', 'content'];

    private const EAGER_LOADS = ['author:id,name', 'translations', 'seoMetas', 'media'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 12);
        $page = (int) ($filters['page'] ?? 1);
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $cacheKey = CacheKey::versioned(BlogPost::class).':public:list:'.$perPage.':'.$page.':loc:'.($present ?? 'none');

        $payload = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->listPayload($request, $perPage, $page, $present),
        );

        return response()->json($payload);
    }

    public function show(Request $request, string $slug)
    {
        $slug = strtolower($slug);
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $cacheKey = CacheKey::versioned(BlogPost::class).':public:show:'.md5($slug).':loc:'.($present ?? 'none');

        $payload = PublicCache::rememberOrMissing(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->showPayload($request, $slug, $present),
        );

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(Request $request, int $perPage, int $page, ?string $present): array
    {
        $query = BlogPost::query()
            ->with(self::EAGER_LOADS)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present, self::CONTENT_FIELDS);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        if ($present) {
            $paginator->setCollection(
                $paginator->getCollection()
                    ->each(fn (BlogPost $post) => TranslatableContentPresenter::applyBlogPost($post, $present))
                    ->filter(fn (BlogPost $post) => TranslatableContentPresenter::hasBlogPostContentForLocale($post, $present))
                    ->values()
            );
        }

        return PublicBlogPostResource::collection($paginator)->response($request)->getData(true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function showPayload(Request $request, string $slug, ?string $present): ?array
    {
        $post = BlogPost::query()
            ->with(self::EAGER_LOADS)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (! $post instanceof BlogPost) {
            return null;
        }

        if ($present) {
            TranslatableContentPresenter::applyBlogPost($post, $present);
            if (! TranslatableContentPresenter::hasBlogPostContentForLocale($post, $present)) {
                return null;
            }
        }

        return (new PublicBlogPostResource($post))->response($request)->getData(true);
    }
}
