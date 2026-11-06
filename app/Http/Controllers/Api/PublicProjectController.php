<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PaginatesPublicListings;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProjectCardResource;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Support\CacheKey;
use App\Support\PublicCache;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Unauthenticated read-only project listing for the marketing site.
 */
class PublicProjectController extends Controller
{
    use PaginatesPublicListings;

    private const CACHE_TTL_SECONDS = 300;

    private const COLUMNS = [
        'id', 'title', 'slug', 'content_locale', 'status', 'featured',
        'published_at', 'summary', 'description',
    ];

    /** Translation columns that make a project count as written in a locale. */
    private const CONTENT_FIELDS = ['title', 'summary', 'description'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'featured' => ['nullable', 'boolean'],
            'category_slug' => ['nullable', 'string', 'max:191'],
            'skill_slug' => ['nullable', 'string', 'max:191'],
            ...$this->publicPagingRules(),
        ]);

        $perPage = (int) ($filters['per_page'] ?? 12);
        $page = (int) ($filters['page'] ?? 1);
        $featured = ! empty($filters['featured']);
        $categorySlug = mb_strtolower(trim((string) ($filters['category_slug'] ?? '')));
        $skillSlug = mb_strtolower(trim((string) ($filters['skill_slug'] ?? '')));
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);

        if (($categorySlug !== '' && ! $this->publicSlugExists(Category::class, $categorySlug))
            || ($skillSlug !== '' && ! $this->publicSlugExists(Skill::class, $skillSlug))) {
            return PublicProjectCardResource::collection(
                new LengthAwarePaginator([], 0, $perPage, $page, ['path' => $request->url()])
            );
        }

        $cacheKey = CacheKey::versioned(Project::class).':public:list:'
            .md5(json_encode([$featured, $categorySlug, $skillSlug, $perPage, $page]))
            .':loc:'.($present ?? 'none');

        $payload = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($request, $featured, $categorySlug, $skillSlug, $perPage, $page, $present) {
            $query = $this->baseQuery()
                ->where('status', 'published')
                ->latest('published_at')
                ->orderByDesc('id');

            TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present, self::CONTENT_FIELDS);

            if ($featured) {
                $query->where('featured', true);
            }
            if ($categorySlug !== '') {
                $query->whereHas('categories', fn ($q) => $q->where('slug', $categorySlug));
            }
            if ($skillSlug !== '') {
                $query->whereHas('skills', fn ($q) => $q->where('slug', $skillSlug));
            }

            return $this->presentPublicPage(
                $request,
                $query->paginate($perPage, ['*'], 'page', $page),
                $present,
                static fn (Project $project, string $locale) => TranslatableContentPresenter::applyProject($project, $locale),
                static fn (Project $project, string $locale) => TranslatableContentPresenter::hasProjectContentForLocale($project, $locale),
                PublicProjectCardResource::class,
            );
        });

        return response()->json($payload);
    }

    /**
     * Single project by slug: published for everyone (cached); draft/archived visible only to authenticated
     * users allowed by ProjectPolicy @see ProjectPolicy::view (editors/admins/companies that manage projects).
     */
    public function show(Request $request, string $slug)
    {
        $slug = strtolower($slug);
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $cacheKey = CacheKey::versioned(Project::class).':public:show:'.md5($slug).':loc:'.($present ?? 'none');

        $payload = PublicCache::rememberOrMissing($cacheKey, self::CACHE_TTL_SECONDS, function () use ($request, $slug, $present) {
            $project = $this->baseQuery()->with('seoMetas')->where('slug', $slug)->where('status', 'published')->first();

            return $project instanceof Project ? $this->detailPayload($request, $project, $present) : null;
        });

        if ($payload !== null) {
            return response()->json($payload);
        }

        $user = $request->user();
        if ($user === null) {
            abort(404);
        }

        $project = $this->baseQuery()->with('seoMetas')->where('slug', $slug)->first();
        if (! $project instanceof Project || ! $user->can('view', $project)) {
            abort(404);
        }

        return response()->json($this->detailPayload($request, $project, $present));
    }

    private function baseQuery(): Builder
    {
        return Project::query()
            ->with([
                'categories:id,name,slug',
                'skills:id,name,slug',
                'translations',
                'media' => fn ($q) => $q->where('collection_name', 'hero'),
            ])
            ->select(self::COLUMNS);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(Request $request, Project $project, ?string $present): array
    {
        if ($present) {
            TranslatableContentPresenter::applyProject($project, $present);
        }

        return (new PublicProjectCardResource($project))->response($request)->getData(true);
    }
}
