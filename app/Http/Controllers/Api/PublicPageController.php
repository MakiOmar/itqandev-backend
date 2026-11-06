<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Appearance\DynamicTagResolver;
use App\Services\Appearance\PageLayoutDocument;
use App\Services\Appearance\WidgetRegistry;
use App\Support\CmsPublicPaths;
use App\Support\PageHierarchy;
use App\Support\PublicCache;
use App\Support\SeoMetaPresenter;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Unauthenticated read-only CMS pages for the marketing site.
 */
class PublicPageController extends Controller
{
    public function index(Request $request)
    {
        $present = TranslatableContentPresenter::requestedPresentationLocale($request)
            ?? SiteLanguages::defaultCode();
        $cacheKey = 'public:pages:list:v2:'.Page::publicCacheVersion().':loc:'.$present;

        $payload = Cache::remember($cacheKey, 300, function () use ($present) {
            $query = Page::query()
                ->select(['id', 'title', 'subtitle', 'slug', 'excerpt', 'content_locale', 'status', 'published_at', 'updated_at', 'parent_id', 'exclude_from_search'])
                ->with('translations')
                ->where('status', Page::STATUS_PUBLISHED)
                ->where('exclude_from_search', false)
                ->orderByDesc('published_at')
                ->orderBy('id');

            TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present);

            $pages = $query->get()
                ->map(function (Page $page) use ($present) {
                    TranslatableContentPresenter::applyPage($page, $present);

                    return $page;
                })
                ->filter(function (Page $page) use ($present) {
                    return TranslatableContentPresenter::hasPageContentForLocale($page, $present);
                })
                ->values();

            $byId = PageHierarchy::publishedGraphById();

            return $pages->map(function (Page $page) use ($byId) {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'subtitle' => $page->subtitle,
                    'slug' => $page->slug,
                    'excerpt' => $page->excerpt,
                    'parent_id' => self::publishedParentId($page, $byId),
                    'path' => PageHierarchy::pathFor($page, $byId, false),
                    'public_path' => CmsPublicPaths::pathForPage($page, $byId, false),
                    'published_at' => $page->published_at?->toIso8601String(),
                ];
            })->values()->all();
        });

        return response()->json($payload);
    }

    public function show(Request $request, string $slug)
    {
        $slug = strtolower($slug);
        $present = TranslatableContentPresenter::requestedPresentationLocale($request)
            ?? SiteLanguages::defaultCode();
        $cacheKey = 'public:pages:show:v'.Page::publicCacheVersion().':'.md5($slug).':loc:'.$present;

        $payload = PublicCache::rememberOrMissing($cacheKey, 300, function () use ($slug, $present) {
            $page = Page::query()
                ->with([
                    'translations',
                    'seoMetas',
                    'parent' => fn ($query) => $query->select(['id', 'title', 'slug', 'parent_id', 'status', 'content_locale']),
                    'parent.translations',
                ])
                ->where('slug', $slug)
                ->where('status', Page::STATUS_PUBLISHED)
                ->first();

            if ($page === null) {
                return null;
            }

            TranslatableContentPresenter::applyPage($page, $present);
            if (! TranslatableContentPresenter::hasPageContentForLocale($page, $present)) {
                return null;
            }

            // `{{post.*}}` tags in the page's own layout resolve against this (already localized) page.
            $sections = PageLayoutDocument::presentPublicForPages(
                is_array($page->sections) ? $page->sections : [],
                $present,
                DynamicTagResolver::payloadFromModel($page, 'page', $present),
            );

            $primary = SiteLanguages::primaryLocaleForContent($page->content_locale);
            $picked = SeoMetaPresenter::pickLocalized(
                $page->relationLoaded('seoMetas') ? $page->seoMetas : null,
                $present,
                $primary,
            );

            $byId = PageHierarchy::publishedAncestorsById($page);

            return [
                'id' => $page->id,
                'title' => $page->title,
                'subtitle' => $page->subtitle,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'content_locale' => $page->content_locale,
                'parent_id' => self::publishedParentId($page, $byId),
                'path' => PageHierarchy::pathFor($page, $byId, false),
                'public_path' => CmsPublicPaths::pathForPage($page, $byId, false),
                'exclude_from_search' => (bool) $page->exclude_from_search,
                'published_at' => $page->published_at?->toIso8601String(),
                'sections' => $sections,
                'canonical_query' => WidgetRegistry::canonicalQueryKeys($sections),
                'seo_meta' => SeoMetaPresenter::toPublicSnippet($picked),
            ];
        });

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload);
    }

    /**
     * @param  array<int, Page>  $publishedById
     */
    private static function publishedParentId(Page $page, array $publishedById): ?int
    {
        $parentId = (int) $page->parent_id;

        return $parentId > 0 && isset($publishedById[$parentId]) ? $parentId : null;
    }
}
