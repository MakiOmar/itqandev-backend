<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChromeLayout;
use App\Services\ActivityLogService;
use App\Services\Appearance\ChromeLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ChromeLayoutController extends Controller
{
    public function __construct(
        private readonly ChromeLayoutService $layouts,
    ) {}

    public function indexHeaders(Request $request): JsonResponse
    {
        return $this->index($request, ChromeLayout::KIND_HEADER);
    }

    public function indexFooters(Request $request): JsonResponse
    {
        return $this->index($request, ChromeLayout::KIND_FOOTER);
    }

    public function storeHeader(Request $request): JsonResponse
    {
        return $this->store($request, ChromeLayout::KIND_HEADER);
    }

    public function storeFooter(Request $request): JsonResponse
    {
        return $this->store($request, ChromeLayout::KIND_FOOTER);
    }

    public function showHeader(int $id): JsonResponse
    {
        return $this->show($id, ChromeLayout::KIND_HEADER);
    }

    public function showFooter(int $id): JsonResponse
    {
        return $this->show($id, ChromeLayout::KIND_FOOTER);
    }

    public function updateHeader(Request $request, int $id): JsonResponse
    {
        return $this->update($request, $id, ChromeLayout::KIND_HEADER);
    }

    public function updateFooter(Request $request, int $id): JsonResponse
    {
        return $this->update($request, $id, ChromeLayout::KIND_FOOTER);
    }

    public function destroyHeader(int $id): Response|JsonResponse
    {
        return $this->destroy($id, ChromeLayout::KIND_HEADER);
    }

    public function destroyFooter(int $id): Response|JsonResponse
    {
        return $this->destroy($id, ChromeLayout::KIND_FOOTER);
    }

    public function setSiteDefaultHeader(Request $request, int $id): JsonResponse
    {
        return $this->setSiteDefault($request, $id, ChromeLayout::KIND_HEADER);
    }

    public function setSiteDefaultFooter(Request $request, int $id): JsonResponse
    {
        return $this->setSiteDefault($request, $id, ChromeLayout::KIND_FOOTER);
    }

    public function showTypeDefaults(): JsonResponse
    {
        $this->authorize('manageSettings');

        return response()->json([
            'success' => true,
            'data' => $this->layouts->getTypeDefaults(),
        ]);
    }

    public function updateTypeDefaults(Request $request): JsonResponse
    {
        $this->authorize('manageSettings');

        $rules = [];
        foreach (ChromeLayoutService::CONTENT_TYPES as $type) {
            $rules[$type] = ['sometimes', 'array'];
            $rules[$type.'.header_id'] = ['nullable', 'integer'];
            $rules[$type.'.footer_id'] = ['nullable', 'integer'];
        }

        $validated = $request->validate($rules);
        $saved = $this->layouts->saveTypeDefaults($validated);

        ActivityLogService::record('appearance.chrome_type_defaults.updated', null, [], $request);

        return response()->json([
            'success' => true,
            'data' => $saved,
            'message' => 'Chrome type defaults saved.',
        ]);
    }

    private function index(Request $request, string $kind): JsonResponse
    {
        $this->authorize('manageSettings');

        $includeDocument = $this->wantsDocument($request);
        $perPage = max(1, min((int) $request->query('per_page', 50), 100));
        $paginator = $this->layouts->list($kind, $includeDocument, $perPage);

        $items = collect($paginator->items())->map(function (ChromeLayout $layout) use ($includeDocument) {
            return $this->serializeLayout($layout, $includeDocument);
        })->values()->all();

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

    private function store(Request $request, string $kind): JsonResponse
    {
        $this->authorize('manageSettings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'status' => ['sometimes', 'string', Rule::in([ChromeLayout::STATUS_DRAFT, ChromeLayout::STATUS_PUBLISHED])],
            'sections' => ['sometimes', 'array'],
        ]);

        $layout = $this->layouts->create($kind, $validated);

        ActivityLogService::record('appearance.'.$kind.'.created', $layout, [
            'slug' => $layout->slug,
        ], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serializeLayout($layout, true),
            'message' => ucfirst($kind).' layout created.',
        ], 201);
    }

    private function show(int $id, string $kind): JsonResponse
    {
        $this->authorize('manageSettings');
        $layout = $this->findForKind($id, $kind);

        return response()->json([
            'success' => true,
            'data' => $this->serializeLayout($layout, true),
        ]);
    }

    private function update(Request $request, int $id, string $kind): JsonResponse
    {
        $this->authorize('manageSettings');
        $layout = $this->findForKind($id, $kind);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'status' => ['sometimes', 'string', Rule::in([ChromeLayout::STATUS_DRAFT, ChromeLayout::STATUS_PUBLISHED])],
            'sections' => ['sometimes', 'array'],
        ]);

        $layout = $this->layouts->update($layout, $validated);

        ActivityLogService::record('appearance.'.$kind.'.updated', $layout, [
            'slug' => $layout->slug,
        ], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serializeLayout($layout, true),
            'message' => ucfirst($kind).' layout saved.',
        ]);
    }

    private function destroy(int $id, string $kind): Response|JsonResponse
    {
        $this->authorize('manageSettings');
        $layout = $this->findForKind($id, $kind);
        $this->layouts->delete($layout);

        ActivityLogService::record('appearance.'.$kind.'.deleted', null, [
            'id' => $id,
        ], request());

        return response()->noContent();
    }

    private function setSiteDefault(Request $request, int $id, string $kind): JsonResponse
    {
        $this->authorize('manageSettings');
        $layout = $this->findForKind($id, $kind);
        $layout = $this->layouts->setSiteDefault($layout);

        ActivityLogService::record('appearance.'.$kind.'.set_site_default', $layout, [
            'slug' => $layout->slug,
        ], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serializeLayout($layout, true),
            'message' => ucfirst($kind).' set as site default.',
        ]);
    }

    private function findForKind(int $id, string $kind): ChromeLayout
    {
        $layout = ChromeLayout::query()->where('kind', $kind)->find($id);
        if ($layout === null) {
            abort(404, ucfirst($kind).' layout not found.');
        }

        return $layout;
    }

    private function wantsDocument(Request $request): bool
    {
        $include = strtolower(trim((string) $request->query('include', '')));

        return $include === 'document' || str_contains($include, 'document');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLayout(ChromeLayout $layout, bool $includeDocument): array
    {
        $data = [
            'id' => $layout->id,
            'kind' => $layout->kind,
            'name' => $layout->name,
            'slug' => $layout->slug,
            'status' => $layout->status,
            'is_site_default' => (bool) $layout->is_site_default,
            'created_at' => $layout->created_at?->toIso8601String(),
            'updated_at' => $layout->updated_at?->toIso8601String(),
        ];

        if ($includeDocument) {
            $document = $this->layouts->adminDocumentPayload($layout);
            $data['document'] = $document;
            $data['sections'] = $document['sections'];
        }

        return $data;
    }
}
