<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'skill' => ['nullable', 'integer', 'exists:skills,id'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $cacheKey = 'projects:list:' . md5(json_encode($filters));

        $projects = Cache::remember(
            $cacheKey,
            now()->addMinutes(15),
            function () use ($filters) {
                $query = Project::with(['categories:id,name', 'skills:id,name', 'seoMeta'])
                    ->select(['id', 'title', 'slug', 'status', 'featured', 'published_at', 'summary', 'description', 'link_url', 'repo_url', 'demo_url'])
                    ->latest('published_at');

                if (!empty($filters['category'])) {
                    $query->whereHas('categories', fn ($q) => $q->where('categories.id', $filters['category']));
                }

                if (!empty($filters['skill'])) {
                    $query->whereHas('skills', fn ($q) => $q->where('skills.id', $filters['skill']));
                }

                if (array_key_exists('featured', $filters)) {
                    $query->where('featured', (bool) $filters['featured']);
                }

                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                return $query->paginate(20);
            }
        );

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'summary' => ['nullable', 'string', 'max:1024'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'max:40'],
            'link_url' => ['nullable', 'url'],
            'repo_url' => ['nullable', 'url'],
            'demo_url' => ['nullable', 'url'],
            'featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ]);

        $project = Project::create($data);
        $project->categories()->sync($data['category_ids'] ?? []);
        $project->skills()->sync($data['skill_ids'] ?? []);

        return response()->json($project->load('categories:id,name', 'skills:id,name'), 201);
    }

    public function show(Project $project)
    {
        $project->load('categories:id,name', 'skills:id,name', 'testimonials', 'seoMeta');

        return response()->json($project);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('projects')->ignore($project->id)],
            'summary' => ['nullable', 'string', 'max:1024'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:40'],
            'link_url' => ['nullable', 'url'],
            'repo_url' => ['nullable', 'url'],
            'demo_url' => ['nullable', 'url'],
            'featured' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ]);

        $project->update($data);

        if (isset($data['category_ids'])) {
            $project->categories()->sync($data['category_ids']);
        }

        if (isset($data['skill_ids'])) {
            $project->skills()->sync($data['skill_ids']);
        }

        return response()->json($project->load('categories:id,name', 'skills:id,name'));
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->noContent();
    }
}
