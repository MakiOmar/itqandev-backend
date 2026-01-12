<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    protected HtmlSanitizerService $sanitizer;

    public function __construct(HtmlSanitizerService $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $filters = $request->validate([
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'skill' => ['nullable', 'integer', 'exists:skills,id'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $query = Project::with([
                'categories:id,name',
                'skills:id,name',
                'seoMeta',
                'media' => function ($query) {
                    $query->whereIn('collection_name', ['hero', 'video', 'gallery']);
                }
            ])
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

        // Create cache key based on filters
        $cacheKey = 'projects:list:' . md5(serialize($filters));
        $page = $request->get('page', 1);
        $cacheKey .= ':page:' . $page;

        $paginator = Cache::remember($cacheKey, 1800, function () use ($query) {
            return $query->paginate(20);
        });

        return ProjectResource::collection($paginator);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

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

        // Sanitize HTML content
        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->sanitize($data['description']);
        }
        if (isset($data['summary'])) {
            $data['summary'] = $this->sanitizer->stripAll($data['summary']);
        }

        $project = Project::create($data);
        $project->categories()->sync($data['category_ids'] ?? []);
        $project->skills()->sync($data['skill_ids'] ?? []);

        $project->load('categories:id,name', 'skills:id,name');
        return (new ProjectResource($project))->response()->setStatusCode(201);
    }

    public function show(Project $project)
    {
        // Explicitly resolve ID from route in case implicit binding is bypassed
        $routeId = $project->id ?: request()->route('project');
        if (!$routeId) {
            abort(404, 'Project not found');
        }

        $project = Project::with([
            'categories:id,name',
            'skills:id,name',
            'testimonials' => function ($query) {
                $query->with('media');
            },
            'seoMeta',
            'media' => function ($query) {
                $query->whereIn('collection_name', ['hero', 'video', 'gallery']);
            }
        ])->findOrFail($routeId);
        
        $this->authorize('view', $project);

        // Load media collections (already eager loaded, but getFirstMedia is more reliable)
        $hero = $project->getFirstMedia('hero');
        $video = $project->getFirstMedia('video');
        
        // Use ProjectResource for consistent response format
        return new ProjectResource($project);
    }


    public function update(Request $request, Project $project)
    {
        // Fallback: If route model binding fails, load manually
        if (!$project->exists) {
            $project = Project::findOrFail($request->route('project'));
        }

        $this->authorize('update', $project);

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

        // Sanitize HTML content
        if (isset($data['description'])) {
            $data['description'] = $this->sanitizer->sanitize($data['description']);
        }
        if (isset($data['summary'])) {
            $data['summary'] = $this->sanitizer->stripAll($data['summary']);
        }

        $project->update($data);

        if (isset($data['category_ids'])) {
            $project->categories()->sync($data['category_ids']);
        }

        if (isset($data['skill_ids'])) {
            $project->skills()->sync($data['skill_ids']);
        }

        $project->load('categories:id,name', 'skills:id,name');
        return new ProjectResource($project);
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        
        $project->delete();

        return response()->noContent();
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('bulkDelete', Project::class);

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
