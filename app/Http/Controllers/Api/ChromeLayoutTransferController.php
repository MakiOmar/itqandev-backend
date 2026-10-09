<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkChromeLayoutsRequest;
use App\Http\Requests\BulkUpdateChromeLayoutStatusRequest;
use App\Http\Requests\ImportChromeLayoutsRequest;
use App\Models\ChromeLayout;
use App\Services\ActivityLogService;
use App\Services\Appearance\ChromeLayoutTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listing-level export/import and bulk actions for Theme Builder layouts
 * (`/api/appearance/{headers|footers|bodies|singles|archives|loop-items|overlays}/…`).
 */
class ChromeLayoutTransferController extends Controller
{
    public function __construct(
        private readonly ChromeLayoutTransferService $transfer,
    ) {}

    public function export(Request $request, string $kind): JsonResponse
    {
        $this->authorize('manageSettings');
        $kind = $this->resolveKind($kind);

        $validated = $request->validate([
            'ids' => ['sometimes', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);
        $ids = isset($validated['ids']) ? array_map('intval', $validated['ids']) : null;

        return response()->json($this->transfer->export($kind, $ids));
    }

    public function import(ImportChromeLayoutsRequest $request): JsonResponse
    {
        $kind = $request->layoutKind();
        $result = $this->transfer->import($kind, $request->validated()['items']);

        ActivityLogService::record('appearance.'.$kind.'.imported', null, [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ], $request);

        return response()->json([
            'success' => true,
            ...$result,
            'message' => 'Imported '.($result['created'] + $result['updated']).' layouts.',
        ]);
    }

    public function bulkDelete(BulkChromeLayoutsRequest $request): JsonResponse
    {
        $kind = $request->layoutKind();
        $ids = $request->validated()['ids'];
        $result = $this->transfer->bulkDelete($kind, $ids);

        ActivityLogService::record('appearance.'.$kind.'.bulk_deleted', null, ['ids' => $ids], $request);

        return response()->json([
            'success' => true,
            ...$result,
            'message' => 'Deleted '.$result['deleted'].' layouts.',
        ]);
    }

    public function bulkStatus(BulkUpdateChromeLayoutStatusRequest $request): JsonResponse
    {
        $kind = $request->layoutKind();
        $validated = $request->validated();
        $result = $this->transfer->bulkSetStatus($kind, $validated['ids'], $validated['status']);

        ActivityLogService::record('appearance.'.$kind.'.bulk_status', null, [
            'ids' => $validated['ids'],
            'status' => $validated['status'],
        ], $request);

        return response()->json([
            'success' => true,
            ...$result,
            'status' => $validated['status'],
            'message' => 'Updated '.$result['updated'].' layouts.',
        ]);
    }

    private function resolveKind(string $segment): string
    {
        $kind = ChromeLayout::kindFromRouteSegment($segment);
        if ($kind === null) {
            abort(404, 'Unknown layout type.');
        }

        return $kind;
    }
}
