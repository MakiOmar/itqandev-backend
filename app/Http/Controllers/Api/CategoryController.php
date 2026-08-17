<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ExportsImportsTranslatableContent;
use App\Http\Controllers\Api\Concerns\PreparesUniqueContentSlug;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\ContentExport\CategoryListCacheInvalidator;
use App\Services\ContentExport\CategoryTranslationSync;
use App\Services\HtmlSanitizerService;
use App\Support\ContentExportEnvelope;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use ExportsImportsTranslatableContent;
    use PreparesUniqueContentSlug;

    private const LIST_CACHE_KEY = 'categories:list:v3:json';

    private const LIST_LOCK_KEY = 'lock:categories:list:v1';

    protected function exportImportEntity(): string
    {
        return ContentExportEnvelope::ENTITY_CATEGORIES;
    }

    protected function exportImportPolicyModel(): string
    {
        return Category::class;
    }

    protected HtmlSanitizerService $sanitizer;

    protected CategoryTranslationSync $translationSync;

    public function __construct(
        HtmlSanitizerService $sanitizer,
        CategoryTranslationSync $translationSync,
    ) {
        $this->sanitizer = $sanitizer;
        $this->translationSync = $translationSync;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Category::class);

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $cacheEnabled = (bool) config('app.sys_cache_enabled', true);

        $key = self::LIST_CACHE_KEY.':loc:'.($present ?? 'none');
        $lockKey = self::LIST_LOCK_KEY;

        $buildJson = function () use ($present): string {
            $query = Category::withCount('projects')
                ->with(['seoMetas', 'media', 'translations'])
                ->orderBy('name')
                ->when($present, function ($query) use ($present) {
                    TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $present);
                });

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

            return json_encode([
                'data' => CategoryResource::collection($categories)->resolve(),
                'meta' => ['cache' => ['hit' => false]],
            ], JSON_UNESCAPED_UNICODE);
        };

        $respond = function (string $json, string $mode) {
            return response($json, 200)
                ->header('Content-Type', 'application/json')
                ->header('X-Cache-Mode', $mode) // hit | miss | bypass
                ->header('X-Cache-Hit', $mode === 'hit' ? '1' : '0');
        };

        // 🚫 Cache disabled via env
        if (! $cacheEnabled) {
            return $respond($buildJson(), 'bypass');
        }

        // ⚡ Fast path: single Redis GET (no has()+get())
        $json = Cache::get($key);
        if (is_string($json)) {
            return $respond($json, 'hit');
        }

        // 🐢 Miss path: rebuild under Redis lock to prevent stampede
        $json = Cache::lock($lockKey, 10)->block(5, function () use ($key, $buildJson) {
            // Re-check after acquiring lock (another worker may have filled it)
            $json = Cache::get($key);
            if (is_string($json)) {
                return $json;
            }

            return Cache::remember($key, 3600, $buildJson);
        });

        return $respond($json, 'miss');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Category::class);

        $this->mergeUniqueContentSlug($request, Category::class, 'name');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:1024'],
            'is_featured' => ['boolean'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:1024'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);

        // Sanitize HTML content
        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->stripAll($data['description']);
        }

        $category = Category::create($data);
        if (is_array($translations)) {
            $this->syncCategoryTranslations($category, $translations);
        }
        $category->load('translations');
        $this->flushListCache();

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category)
    {
        $this->authorize('view', $category);

        $category->load([
            'seoMetas',
            'projects:id,title',
            'translations',
            'media' => function ($query) {
                $query->whereIn('collection_name', ['icon', 'thumb', 'banner']);
            },
        ]);

        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $this->mergeUniqueContentSlug($request, Category::class, 'name', (int) $category->id, true);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1024'],
            'is_featured' => ['boolean'],
            'content_locale' => ['nullable', 'string', 'max:16'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:1024'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }

        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->stripAll($data['description']);
        }

        $category->update($data);
        if (is_array($translations)) {
            $this->syncCategoryTranslations($category, $translations);
        }
        $category->load('translations');
        $this->flushListCache();

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();
        $this->flushListCache();
        // Cache invalidation handled by InvalidatesCache trait

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Category::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $count = Category::whereIn('id', $data['ids'])->delete();
        $this->flushListCache();
        // Cache invalidation handled by InvalidatesCache trait on model events

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted '.$count.' categories',
        ]);
    }

    private function flushListCache(): void
    {
        CategoryListCacheInvalidator::flush();
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncCategoryTranslations(Category $category, array $translations): void
    {
        $this->translationSync->sync($category, $translations);
    }
}
