<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\HtmlSanitizerService;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;


class CategoryController extends Controller
{
    private const LIST_CACHE_KEY = 'categories:list:v1:json';
    private const LIST_LOCK_KEY = 'lock:categories:list:v1';

    protected HtmlSanitizerService $sanitizer;

    public function __construct(HtmlSanitizerService $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public function index(Request $request)
    {
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);
        $siteDefaultLocale = SiteLanguages::defaultCode();
        $cacheEnabled = (bool) config('app.sys_cache_enabled', true);

        $key = self::LIST_CACHE_KEY . ':loc:' . ($present ?? 'none');
        $lockKey = self::LIST_LOCK_KEY;

        $buildJson = function () use ($present, $siteDefaultLocale): string {
            $query = Category::withCount('projects')
                ->with(['seoMeta', 'media', 'translations'])
                ->orderBy('name')
                ->when($present, function ($query) use ($present, $siteDefaultLocale) {
                    $query->where(function ($q) use ($present, $siteDefaultLocale) {
                        $q->where('content_locale', $present);
                        if ($present === $siteDefaultLocale) {
                            $q->orWhereNull('content_locale');
                        }
                        $q->orWhereHas('translations', function ($tq) use ($present) {
                            $tq->where('locale', $present);
                        });
                    });
                })
                ;

            $categories = $query->get();

            if ($present) {
                $categories->transform(function (Category $category) use ($present) {
                    TranslatableContentPresenter::applyCategory($category, $present);
                    return $category;
                });
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
        if (!$cacheEnabled) {
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
            'seoMeta',
            'projects:id,title',
            'translations',
            'media' => function ($query) {
                $query->whereIn('collection_name', ['icon', 'thumb', 'banner']);
            }
        ]);

        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

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
            'message' => 'Deleted ' . $count . ' categories',
        ]);
    }

    private function flushListCache(): void
    {
        // Index caches per locale (including "none" when header is absent).
        Cache::forget(self::LIST_CACHE_KEY); // legacy / safety
        Cache::forget(self::LIST_CACHE_KEY . ':loc:none');
        foreach (SiteLanguages::all() as $row) {
            $code = is_array($row) && isset($row['code']) ? (string) $row['code'] : '';
            if ($code !== '') {
                Cache::forget(self::LIST_CACHE_KEY . ':loc:' . strtolower($code));
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncCategoryTranslations(Category $category, array $translations): void
    {
        $category->refresh();
        $allowed = array_flip(SiteLanguages::secondaryLocaleCodesForContent($category->content_locale));
        $category->translations()->whereNotIn('locale', array_keys($allowed))->delete();

        if ($allowed === []) {
            return;
        }

        foreach ($translations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $locale = strtolower(trim((string) ($row['locale'] ?? '')));
            if ($locale === '' || ! isset($allowed[$locale])) {
                continue;
            }

            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $description = isset($row['description']) ? trim((string) $row['description']) : '';

            if ($name === '' && $description === '') {
                $category->translations()->where('locale', $locale)->delete();
                continue;
            }

            $category->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $name !== '' ? $name : null,
                    'description' => $description !== '' ? $this->sanitizer->stripAll($description) : null,
                ]
            );
        }
    }
}
