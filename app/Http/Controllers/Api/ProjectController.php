<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Http\Request;
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

        return response()->json($query->paginate(20));
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
        // Explicitly resolve ID from route in case implicit binding is bypassed
        $routeId = $project->id ?: request()->route('project');
        if (!$routeId) {
            abort(404, 'Project not found');
        }

        $project = Project::with('categories:id,name', 'skills:id,name', 'testimonials', 'seoMeta')->findOrFail($routeId);

        return response()->json($project);
    }

    public function update(Request $request, Project $project)
    {
        \Log::info('ProjectController::update called', [
            'project_id' => $project->id ?? 'NULL',
            'project_object' => $project ? get_class($project) : 'NULL',
            'route_name' => $request->route()->getName(),
            'route_uri' => $request->route()->uri(),
            'request_url' => $request->fullUrl(),
            'request_path' => $request->path(),
            'request_method' => $request->method(),
            'route_params' => $request->route()->parameters(),
            'request_data' => $request->all(),
        ]);

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

        \Log::info('ProjectController::update validated data', $data);

        $project->update($data);

        if (isset($data['category_ids'])) {
            $project->categories()->sync($data['category_ids']);
        }

        if (isset($data['skill_ids'])) {
            $project->skills()->sync($data['skill_ids']);
        }

        \Log::info('ProjectController::update completed successfully');

        return response()->json($project->load('categories:id,name', 'skills:id,name'));
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $count = Project::whereIn('id', $data['ids'])->delete();

        return response()->json([
            'deleted' => $count,
            'message' => 'Deleted ' . $count . ' projects',
        ]);
    }
}
