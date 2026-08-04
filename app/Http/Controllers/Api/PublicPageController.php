<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Appearance\ContentSectionDocument;
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
        $cacheKey = 'public:pages:list:v'.$version.':loc:'.$present;

        $pages = Cache::remember($cacheKey, 300, function () use ($present) {
            $query = Page::query()
                ->select(['id', 'title', 'slug', 'excerpt', 'content_locale', 'status', 'published_at', 'updated_at'])
                ->with('translations')
                ->where('status', Page::STATUS_PUBLISHED)
                ->orderByDesc('published_at')
                ->orderBy('id');

            TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present);

            return $query->get()
                ->map(function (Page $page) use ($present) {
                    TranslatableContentPresenter::applyPage($page, $present);

                    return $page;
                })
                ->filter(function (Page $page) use ($present) {
                    return TranslatableContentPresenter::hasPageContentForLocale($page, $present);
                })
                ->values()
                ->map(fn (Page $page) => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'excerpt' => $page->excerpt,
                    'published_at' => $page->published_at?->toIso8601String(),
                ]);
        });

        return response()->json($pages);
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

            $sections = ContentSectionDocument::presentPublic(
                is_array($page->sections) ? $page->sections : [],
                $present,
            );

            $primary = SiteLanguages::primaryLocaleForContent($page->content_locale);
            $picked = SeoMetaPresenter::pickLocalized(
                $page->relationLoaded('seoMetas') ? $page->seoMetas : null,
                $present,
                $primary,
            );

            return [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'content_locale' => $page->content_locale,
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
