<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThemeTemplate;
use App\Services\ActivityLogService;
use App\Services\Appearance\ThemeTemplateConditions;
use App\Services\Appearance\ThemeTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ThemeTemplateController extends Controller
{
    public function __construct(
        private readonly ThemeTemplateService $templates,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageSettings');

        $perPage = max(1, min((int) $request->query('per_page', 50), 100));
        $paginator = $this->templates->list($perPage);
        $items = collect($paginator->items())->map(fn (ThemeTemplate $t) => $this->serialize($t))->values()->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manageSettings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['sometimes', 'string', Rule::in([ThemeTemplate::STATUS_DRAFT, ThemeTemplate::STATUS_PUBLISHED])],
            'conditions' => ['sometimes'],
            'header_layout_id' => ['nullable', 'integer'],
            'footer_layout_id' => ['nullable', 'integer'],
            'body_layout_id' => ['nullable', 'integer'],
        ]);

        $template = $this->templates->create($validated);

        ActivityLogService::record('appearance.theme_template.created', $template, [
            'name' => $template->name,
        ], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($template),
            'message' => 'Theme template created.',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('manageSettings');
        $template = $this->find($id);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($template),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('manageSettings');
        $template = $this->find($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'string', Rule::in([ThemeTemplate::STATUS_DRAFT, ThemeTemplate::STATUS_PUBLISHED])],
            'conditions' => ['sometimes'],
            'header_layout_id' => ['nullable', 'integer'],
            'footer_layout_id' => ['nullable', 'integer'],
            'body_layout_id' => ['nullable', 'integer'],
        ]);

        $template = $this->templates->update($template, $validated);

        ActivityLogService::record('appearance.theme_template.updated', $template, [
            'name' => $template->name,
        ], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($template),
            'message' => 'Theme template saved.',
        ]);
    }

    public function destroy(int $id): Response|JsonResponse
    {
        $this->authorize('manageSettings');
        $template = $this->find($id);
        $this->templates->delete($template);

        ActivityLogService::record('appearance.theme_template.deleted', null, [
            'id' => $id,
        ], request());

        return response()->noContent();
    }

    private function find(int $id): ThemeTemplate
    {
        $template = ThemeTemplate::query()->find($id);
        if ($template === null) {
            abort(404, 'Theme template not found.');
        }

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ThemeTemplate $template): array
    {
        $conditions = ThemeTemplateConditions::normalize($template->conditions);

        return [
            'id' => $template->id,
            'name' => $template->name,
            'status' => $template->status,
            'conditions' => $conditions,
            'header_layout_id' => $template->header_layout_id,
            'footer_layout_id' => $template->footer_layout_id,
            'body_layout_id' => $template->body_layout_id,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
