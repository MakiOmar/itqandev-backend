<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ExportsImportsTranslatableContent;
use App\Http\Controllers\Api\Concerns\PreparesUniqueContentSlug;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Appearance\PageLayoutDocument;
use App\Support\CmsPublicPaths;
use App\Support\ContentExportEnvelope;
use App\Support\PageHierarchy;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    use ExportsImportsTranslatableContent;
    use PreparesUniqueContentSlug;

    private const LIST_CACHE_KEY = 'pages:list:v2:json';

    protected function exportImportEntity(): string
    {
        return ContentExportEnvelope::ENTITY_PAGES;
    }

    protected function exportImportPolicyModel(): string
    {
        return Page::class;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Page::class);

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $version = (int) Cache::get('pages:cache_version', 1);

        return response()->json(
            Cache::remember(self::LIST_CACHE_KEY.':v'.$version.':loc:'.($present ?? 'none'), 3600, function () use ($present) {
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

                return $this->withHierarchyFields($pages);
            })
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Page::class);

        $this->normalizeHierarchyInput($request);
        $this->mergeUniqueContentSlug($request, Page::class, 'title');

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
            'header_layout_id' => ['nullable', 'integer'],
            'footer_layout_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer', 'exists:pages,id'],
            'exclude_from_search' => ['sometimes', 'boolean'],
        ]);

        PageHierarchy::assertValidParent(
            array_key_exists('parent_id', $data) ? ($data['parent_id'] !== null ? (int) $data['parent_id'] : null) : null,
            null,
        );

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        $data = app(\App\Services\Appearance\ChromeLayoutService::class)->applyAssignmentFields($data);
        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        $data['status'] = $data['status'] ?? Page::STATUS_DRAFT;
        $data['sections'] = PageLayoutDocument::normalizeSectionsForPages($data['sections'] ?? []);
        if ($data['status'] === Page::STATUS_PUBLISHED && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $page = Page::create($data);
        if (is_array($translations)) {
            $this->syncPageTranslations($page, $translations);
        }
        $page->load(['translations', 'seoMetas']);
        $this->bumpPageCaches();

        return response()->json($this->withHierarchyFields([$page])[0] ?? $page, 201);
    }

    public function show(Page $page)
    {
        $this->authorize('view', $page);
        $page->load(['translations', 'seoMetas']);

        return response()->json($this->withHierarchyFields([$page])[0] ?? $page);
    }

    public function update(Request $request, Page $page)
    {
        $this->authorize('update', $page);

        $this->normalizeHierarchyInput($request);
        $this->mergeUniqueContentSlug($request, Page::class, 'title', (int) $page->id, true);

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
            'header_layout_id' => ['nullable', 'integer'],
            'footer_layout_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer', 'exists:pages,id'],
            'exclude_from_search' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('parent_id', $data)) {
            PageHierarchy::assertValidParent(
                $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
                (int) $page->id,
            );
        }

        $translations = $data['translations'] ?? null;
        unset($data['translations']);
        $data = app(\App\Services\Appearance\ChromeLayoutService::class)->applyAssignmentFields($data);
        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }
        if (array_key_exists('sections', $data)) {
            $data['sections'] = PageLayoutDocument::normalizeSectionsForPages($data['sections']);
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

        return response()->json($this->withHierarchyFields([$page])[0] ?? $page);
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

    /**
     * Empty string from native selects means “no parent”.
     */
    private function normalizeHierarchyInput(Request $request): void
    {
        if ($request->exists('parent_id') && $request->input('parent_id') === '') {
            $request->merge(['parent_id' => null]);
        }
    }

    /**
     * Attach nested path, public URL, and tree depth for admin/public JSON.
     *
     * @param  iterable<int, Page>  $pages
     * @return list<Page>
     */
    private function withHierarchyFields(iterable $pages): array
    {
        $graph = Page::query()->select(['id', 'slug', 'parent_id'])->get();
        $byId = PageHierarchy::indexById($graph);
        $flat = PageHierarchy::flattenForAdmin($pages);
        foreach ($flat as $page) {
            $page->setAttribute('path', PageHierarchy::pathFor($page, $byId));
            $page->setAttribute('public_path', CmsPublicPaths::pathForPage($page, $byId));
        }

        return $flat;
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
