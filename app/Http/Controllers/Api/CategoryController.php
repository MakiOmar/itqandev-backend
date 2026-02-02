<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

// optional


class CategoryController extends Controller
{
    protected HtmlSanitizerService $sanitizer;

    public function __construct(HtmlSanitizerService $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public function index(Request $request)
    {
        $cacheEnabled = (bool) config('cache.sys_cache_enabled');

        $key     = 'categories:list:v1:json';
        $lockKey = 'lock:categories:list:v1';

        $buildJson = function (): string {
            $categories = Category::withCount('projects')
                ->with(['seoMeta', 'media'])
                ->orderBy('name')
                ->get();

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
        ]);

        // Sanitize HTML content
        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->stripAll($data['description']);
        }

        $category = Category::create($data);
        Cache::forget('categories:list');

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category)
    {
        $this->authorize('view', $category);

        $category->load([
            'seoMeta',
            'projects:id,title',
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
        ]);

        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->stripAll($data['description']);
        }

        $category->update($data);

        return new CategoryResource($category);
    }


    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();
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
        // Cache invalidation handled by InvalidatesCache trait on model events

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted ' . $count . ' categories',
        ]);
    }
}
