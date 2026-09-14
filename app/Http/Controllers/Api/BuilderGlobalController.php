<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuilderGlobalRequest;
use App\Http\Requests\UpdateBuilderGlobalRequest;
use App\Models\BuilderGlobal;
use App\Services\ActivityLogService;
use App\Services\Appearance\BuilderRevisionService;
use App\Services\Appearance\GlobalWidgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BuilderGlobalController extends Controller
{
    public function __construct(
        private readonly GlobalWidgetService $globals,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('manageSettings');
        $rows = BuilderGlobal::query()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (BuilderGlobal $g) => $this->serialize($g))->values()->all(),
        ]);
    }

    public function store(StoreBuilderGlobalRequest $request): JsonResponse
    {
        $global = $this->globals->create($request->validated());
        BuilderRevisionService::snapshot($global, is_array($global->document) ? $global->document : []);
        ActivityLogService::record('appearance.global.created', $global, ['name' => $global->name], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($global, true),
            'message' => 'Global widget created.',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('manageSettings');

        return response()->json([
            'success' => true,
            'data' => $this->serialize($this->find($id), true),
        ]);
    }

    public function update(UpdateBuilderGlobalRequest $request, int $id): JsonResponse
    {
        $global = $this->globals->update($this->find($id), $request->validated());
        BuilderRevisionService::snapshot($global, is_array($global->document) ? $global->document : []);
        ActivityLogService::record('appearance.global.updated', $global, ['name' => $global->name], $request);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($global, true),
            'message' => 'Global widget saved.',
        ]);
    }

    public function destroy(int $id): Response
    {
        $this->authorize('manageSettings');
        $this->find($id)->delete();
        ActivityLogService::record('appearance.global.deleted', null, ['id' => $id], request());

        return response()->noContent();
    }

    private function find(int $id): BuilderGlobal
    {
        $global = BuilderGlobal::query()->find($id);
        if ($global === null) {
            abort(404, 'Global widget not found.');
        }

        return $global;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BuilderGlobal $global, bool $includeDocument = false): array
    {
        $data = [
            'id' => $global->id,
            'name' => $global->name,
            'slug' => $global->slug,
            'status' => $global->status,
            'created_at' => $global->created_at?->toIso8601String(),
            'updated_at' => $global->updated_at?->toIso8601String(),
        ];
        if ($includeDocument) {
            $data['document'] = $global->document;
        }

        return $data;
    }
}
