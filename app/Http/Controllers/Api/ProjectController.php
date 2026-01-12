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

        // Load media collections
        $hero = $project->getFirstMedia('hero');
        $video = $project->getFirstMedia('video');
        
        // Add media to response
        $projectData = $project->toArray();
        $projectData['media'] = [
            'hero' => $hero ? $this->transformMediaItem($hero) : null,
            'video' => $video ? $this->transformMediaItem($video) : null,
        ];

        return response()->json($projectData);
    }

    /**
     * Transform a single media item for API response.
     */
    protected function transformMediaItem($media): array
    {
        $url = $media->getUrl();
        // Ensure URL is absolute
        if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url($url);
        }
        
        return [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'name' => $media->name,
            'collection' => $media->collection_name,
            'collection_name' => $media->collection_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $url,
            'model_type' => $media->model_type,
            'model_id' => $media->model_id,
            'created_at' => $media->created_at?->toIso8601String(),
            'alt_text' => $media->getCustomProperty('alt_text'),
        ];
    }

    public function update(Request $request, Project $project)
    {
        // Fallback: If route model binding fails, load manually
        if (!$project->exists) {
            $project = Project::findOrFail($request->route('project'));
        }

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
