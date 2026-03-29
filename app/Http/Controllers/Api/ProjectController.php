<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Category;
use App\Models\Project;
use App\Models\Skill;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

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
                'translations',
                'media' => function ($query) {
                    $query->whereIn('collection_name', ['hero', 'video', 'gallery']);
                }
            ])
            ->select(['id', 'title', 'slug', 'content_locale', 'status', 'featured', 'published_at', 'summary', 'description', 'link_url', 'repo_url', 'demo_url'])
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

        $present = TranslatableContentPresenter::requestedPresentationLocale($request);

        // Create cache key based on filters + presentation locale
        $cacheKey = 'projects:list:'.md5(serialize($filters));
        $page = $request->get('page', 1);
        $cacheKey .= ':page:'.$page.':loc:'.($present ?? 'none');

        $paginator = Cache::remember($cacheKey, 1800, function () use ($query) {
            return $query->paginate(20);
        });

        if ($present) {
            $paginator->getCollection()->transform(function (Project $project) use ($present) {
                TranslatableContentPresenter::applyProject($project, $present);

                return $project;
            });
        }

        return ProjectResource::collection($paginator);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        // Handle camelCase inputs from frontend
        $request->merge([
            'link_url' => $request->input('link_url') ?: $request->input('linkUrl'),
            'repo_url' => $request->input('repo_url') ?: $request->input('repoUrl'),
            'demo_url' => $request->input('demo_url') ?: $request->input('demoUrl'),
            'published_at' => $request->input('published_at') ?: $request->input('publishedAt'),
        ]);

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
            'content_locale' => ['nullable', 'string', 'max:16'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.summary' => ['nullable', 'string', 'max:1024'],
            'translations.*.description' => ['nullable', 'string'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);

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

        if (is_array($translations)) {
            $this->syncProjectTranslations($project, $translations);
        }

        $project->load('categories:id,name', 'skills:id,name', 'translations');
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
            'translations',
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
        // Debug: Log all incoming request data
        Log::info('ProjectController@update - Raw request data:', [
            'all' => $request->all(),
            'link_url' => $request->input('link_url'),
            'repo_url' => $request->input('repo_url'),
            'demo_url' => $request->input('demo_url'),
            'published_at' => $request->input('published_at'),
            'linkUrl' => $request->input('linkUrl'),
            'repoUrl' => $request->input('repoUrl'),
            'demoUrl' => $request->input('demoUrl'),
            'publishedAt' => $request->input('publishedAt'),
        ]);

        // Handle camelCase inputs from frontend
        $request->merge([
            'link_url' => $request->input('link_url') ?: $request->input('linkUrl'),
            'repo_url' => $request->input('repo_url') ?: $request->input('repoUrl'),
            'demo_url' => $request->input('demo_url') ?: $request->input('demoUrl'),
            'published_at' => $request->input('published_at') ?: $request->input('publishedAt'),
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
            'content_locale' => ['nullable', 'string', 'max:16'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'skill_ids' => ['array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required', 'string', 'max:16'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.summary' => ['nullable', 'string', 'max:1024'],
            'translations.*.description' => ['nullable', 'string'],
        ]);

        $translations = $data['translations'] ?? null;
        unset($data['translations']);

        if (array_key_exists('content_locale', $data)) {
            $data['content_locale'] = SiteLanguages::normalizeContentLocale($data['content_locale'] ?? null);
        }

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

        if (is_array($translations)) {
            $this->syncProjectTranslations($project, $translations);
        }

        $project->load('categories:id,name', 'skills:id,name', 'translations');
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

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncProjectTranslations(Project $project, array $translations): void
    {
        $project->refresh();
        $allowed = array_flip(SiteLanguages::secondaryLocaleCodesForContent($project->content_locale));
        $project->translations()->whereNotIn('locale', array_keys($allowed))->delete();

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

            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            $summary = isset($row['summary']) ? trim((string) $row['summary']) : '';
            $description = isset($row['description']) ? trim((string) $row['description']) : '';

            if ($title === '' && $summary === '' && $description === '') {
                $project->translations()->where('locale', $locale)->delete();

                continue;
            }

            $project->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'title' => $title !== '' ? $title : null,
                    'summary' => $summary !== '' ? $this->sanitizer->stripAll($summary) : null,
                    'description' => $description !== '' ? $this->sanitizer->sanitize($description) : null,
                ]
            );
        }
    }
}
