<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Appearance\PageLayoutDocument;
use App\Support\CmsPublicPaths;
use App\Support\PageHierarchy;
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
        $version = (int) Cache::get('pages:cache_version', 1);
        $cacheKey = 'public:pages:list:v2:'.$version.':loc:'.$present;

        $payload = Cache::remember($cacheKey, 300, function () use ($present) {
            $query = Page::query()
                ->select(['id', 'title', 'slug', 'excerpt', 'content_locale', 'status', 'published_at', 'updated_at', 'parent_id', 'exclude_from_search'])
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

            $graph = Page::query()->select(['id', 'slug', 'parent_id'])->get();
            $byId = PageHierarchy::indexById($graph);

            return $pages->map(function (Page $page) use ($byId) {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'excerpt' => $page->excerpt,
                    'parent_id' => $page->parent_id,
                    'path' => PageHierarchy::pathFor($page, $byId),
                    'public_path' => CmsPublicPaths::pathForPage($page, $byId),
                    'published_at' => $page->published_at?->toIso8601String(),
                ];
            })->values()->all();
        });

        return response()->json($payload);
    }

    public function show(Request $request, string $slug)
    {
        $present = TranslatableContentPresenter::requestedPresentationLocale($request)
            ?? SiteLanguages::defaultCode();
        $version = (int) Cache::get('pages:cache_version', 1);
        $cacheKey = 'public:pages:show:v'.$version.':'.md5($slug).':loc:'.$present;

        $payload = Cache::remember($cacheKey, 300, function () use ($slug, $present) {
            $page = Page::query()
                ->with(['translations', 'seoMetas'])
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

            $sections = PageLayoutDocument::presentPublicForPages(
                is_array($page->sections) ? $page->sections : [],
                $present,
            );

            $primary = SiteLanguages::primaryLocaleForContent($page->content_locale);
            $picked = SeoMetaPresenter::pickLocalized(
                $page->relationLoaded('seoMetas') ? $page->seoMetas : null,
                $present,
                $primary,
            );

            $graph = Page::query()->select(['id', 'slug', 'parent_id'])->get();
            $byId = PageHierarchy::indexById($graph);

            return [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'content_locale' => $page->content_locale,
                'parent_id' => $page->parent_id,
                'path' => PageHierarchy::pathFor($page, $byId),
                'public_path' => CmsPublicPaths::pathForPage($page, $byId),
                'exclude_from_search' => (bool) $page->exclude_from_search,
                'published_at' => $page->published_at?->toIso8601String(),
                'sections' => $sections,
                'seo_meta' => SeoMetaPresenter::toPublicSnippet($picked),
            ];
        });

        if ($payload === null) {
            abort(404);
        }

        return response()->json($payload);
    }
}
