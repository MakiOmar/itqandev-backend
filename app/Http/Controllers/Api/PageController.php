<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Appearance\ContentSectionDocument;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    private const LIST_CACHE_KEY = 'pages:list:v1:json';

    public function index(Request $request)
    {
        $this->authorize('viewAny', Page::class);

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);

        return response()->json(
            Cache::remember(self::LIST_CACHE_KEY.':loc:'.($present ?? 'none'), 3600, function () use ($present) {
                $query = Page::query()
                    ->with('translations')
                    ->orderByDesc('updated_at')
                    ->orderBy('id')
                    ->when($present, function ($query) use ($present) {
                        TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present);
                    });

                $pages = $query->get();

                if ($present) {
                    $pages = $pages
                        ->map(function (Page $page) use ($present) {
                            TranslatableContentPresenter::applyPage($page, $present);

                            return $page;
                        })
                        ->filter(function (Page $page) use ($present) {
                            return TranslatableContentPresenter::hasPageContentForLocale($page, $present);
                        })
                        ->values();
                }

                return $pages;
            })
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Page::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:pages,slug'],
            'excerpt' => ['nullable', 'string', 'max:512'],
            'status' => ['sometimes', 'string', Rule::in([Page::STATUS_DRAFT, Page::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'sections' => ['nullable', 'array'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:512'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        $data['status'] = $data['status'] ?? Page::STATUS_DRAFT;
        $data['sections'] = ContentSectionDocument::normalizeSections($data['sections'] ?? [], false);
        if ($data['status'] === Page::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $page = Page::create($data);
        if (is_array($translations)) {
            $this->syncPageTranslations($page, $translations);
        }
        $page->load(['translations', 'seoMetas']);
        $this->bumpPageCaches();

        return response()->json($page, 201);
    }

    public function show(Page $page)
    {
        $this->authorize('view', $page);
        $page->load(['translations', 'seoMetas']);

        return response()->json($page);
    }

    public function update(Request $request, Page $page)
    {
        $this->authorize('update', $page);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('pages')->ignore($page->id)],
            'excerpt' => ['nullable', 'string', 'max:512'],
            'status' => ['sometimes', 'string', Rule::in([Page::STATUS_DRAFT, Page::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'sections' => ['nullable', 'array'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:512'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }
        if (array_key_exists('sections', $data)) {
            $data['sections'] = ContentSectionDocument::normalizeSections($data['sections'], false);
        }
        if (($data['status'] ?? $page->status) === Page::STATUS_PUBLISHED
            && empty($data['published_at'])
            && $page->published_at === null) {
            $data['published_at'] = now();
        }

        $page->update($data);
        if (is_array($translations)) {
            $this->syncPageTranslations($page, $translations);
        }
        $page->load(['translations', 'seoMetas']);
        $this->bumpPageCaches();

        return response()->json($page);
    }

    public function destroy(Page $page)
    {
        $this->authorize('delete', $page);
        $page->delete();
        $this->bumpPageCaches();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Page::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:pages,id'],
        ]);

        $count = Page::whereIn('id', $data['ids'])->delete();
        $this->bumpPageCaches();

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted '.$count.' pages',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $translations
     */
    private function syncPageTranslations(Page $page, array $translations): void
    {
        $enabled = [];
        foreach (SiteLanguages::all() as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if ($code !== '') {
                $enabled[$code] = true;
            }
        }
        $primary = SiteLanguages::primaryLocaleForContent($page->content_locale);
        $keep = [];

        foreach ($translations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $locale = strtolower(trim((string) ($row['locale'] ?? '')));
            if ($locale === '' || $locale === $primary || ! isset($enabled[$locale])) {
                continue;
            }
            $page->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => isset($row['title']) ? trim((string) $row['title']) : null,
                    'excerpt' => isset($row['excerpt']) ? trim((string) $row['excerpt']) : null,
                ]
            );
            $keep[] = $locale;
        }

        $page->translations()
            ->whereNotIn('locale', $keep)
            ->delete();
    }

    private function bumpPageCaches(): void
    {
        $version = (int) Cache::get('pages:cache_version', 1);
        Cache::forever('pages:cache_version', $version + 1);
        Cache::forget(self::LIST_CACHE_KEY.':loc:none');
        Cache::forget('public:pages:list:loc:none');
        foreach (SiteLanguages::all() as $row) {
            $code = isset($row['code']) ? strtolower(trim((string) $row['code'])) : '';
            if ($code === '') {
                continue;
            }
            Cache::forget(self::LIST_CACHE_KEY.':loc:'.$code);
            Cache::forget('public:pages:list:loc:'.$code);
        }
    }
}
