<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            Category::withCount('projects')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:1024'],
            'is_featured' => ['boolean'],
        ]);

        $category = Category::create($data);
        Cache::forget('categories:list');

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        $category->load('seoMeta', 'projects:id,title');

        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1024'],
            'is_featured' => ['boolean'],
        ]);

        $category->update($data);
        Cache::forget('categories:list');

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        Cache::forget('categories:list');

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $count = Category::whereIn('id', $data['ids'])->delete();
        Cache::forget('categories:list');

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted ' . $count . ' categories',
        ]);
    }
}
