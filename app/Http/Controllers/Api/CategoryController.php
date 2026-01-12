<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    protected HtmlSanitizerService $sanitizer;

    public function __construct(HtmlSanitizerService $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public function index()
    {
        $this->authorize('viewAny', Category::class);

        $categories = Cache::remember('categories:list', 3600, function () {
            return Category::withCount('projects')
                ->with(['seoMeta', 'media'])
                ->orderBy('name')
                ->get();
        });

        return CategoryResource::collection($categories);
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

        // Sanitize HTML content
        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->stripAll($data['description']);
        }

        $category->update($data);
        // Cache invalidation handled by InvalidatesCache trait

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
