<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteBuilderTemplatesRequest;
use App\Http\Requests\StoreBuilderTemplateRequest;
use App\Http\Requests\UpdateBuilderTemplateRequest;
use App\Models\BuilderTemplate;
use App\Services\ActivityLogService;
use App\Services\Appearance\BuilderTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BuilderTemplateController extends Controller
{
    public function __construct(
        private readonly BuilderTemplateService $templates,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BuilderTemplate::class);
        $filters = $request->validate([
            'kind' => ['sometimes', 'string', Rule::in(BuilderTemplate::KINDS)],
        ]);

        $rows = BuilderTemplate::query()
            ->when($filters['kind'] ?? null, fn ($q, string $kind) => $q->where('kind', $kind))
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (BuilderTemplate $t) => $this->serialize($t))->values()->all(),
        ]);
    }

    public function store(StoreBuilderTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->create($request->validated(), $request->user()?->id);
        ActivityLogService::record('appearance.template.created', $template, ['name' => $template->name], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($template, true),
            'message' => 'Template saved.',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $template = $this->find($id);
        $this->authorize('view', $template);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($template, true),
        ]);
    }

    public function update(UpdateBuilderTemplateRequest $request, int $id): JsonResponse
    {
        $template = $this->templates->update($this->find($id), $request->validated());
        ActivityLogService::record('appearance.template.updated', $template, ['name' => $template->name], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($template, true),
            'message' => 'Template saved.',
        ]);
    }

    public function destroy(int $id): Response
    {
        $template = $this->find($id);
        $this->authorize('delete', $template);
        $template->delete();
        ActivityLogService::record('appearance.template.deleted', null, ['id' => $id, 'name' => $template->name], request());

        return response()->noContent();
    }

    public function bulkDelete(BulkDeleteBuilderTemplatesRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];
        $count = BuilderTemplate::query()->whereIn('id', $ids)->delete();
        ActivityLogService::record('appearance.template.bulk_deleted', null, ['ids' => $ids], $request);

        return response()->json([
            'success' => true,
            'deleted' => $count,
            'message' => 'Deleted '.$count.' templates.',
        ]);
    }

    private function find(int $id): BuilderTemplate
    {
        $template = BuilderTemplate::query()->find($id);
        if ($template === null) {
            abort(404, 'Template not found.');
        }

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BuilderTemplate $template, bool $includeDocument = false): array
    {
        $data = [
            'id' => $template->id,
            'name' => $template->name,
            'kind' => $template->kind,
            'block_type' => BuilderTemplateService::blockType($template),
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
        if ($includeDocument) {
            $data['document'] = $template->document;
        }

        return $data;
    }
}
