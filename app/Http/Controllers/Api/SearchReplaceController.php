<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\System\SearchReplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SearchReplaceController extends Controller
{
    public function __construct(private readonly SearchReplaceService $searchReplace)
    {
    }

    public function tables(Request $request): JsonResponse
    {
        $this->authorize('manageSystemCache');
        unset($request);

        return response()->json([
            'data' => $this->searchReplace->listTables(),
            'meta' => [
                'confirm_phrase' => $this->searchReplace->confirmPhrase(),
                'driver' => config('database.default'),
            ],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('manageSystemCache');

        $validated = $request->validate([
            'find' => ['required', 'string', 'min:1', 'max:5000'],
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['required', 'string', 'max:128'],
            'case_sensitive' => ['sometimes', 'boolean'],
            'ignore_slugs' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->searchReplace->preview(
                $validated['tables'],
                $validated['find'],
                (bool) ($validated['case_sensitive'] ?? false),
                (bool) ($validated['ignore_slugs'] ?? true),
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => $this->safeMessage($e, 'Search preview failed.'),
            ], 422);
        }

        return response()->json([
            'message' => 'Preview complete.',
            'data' => $result,
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $this->authorize('manageSystemCache');

        $validated = $request->validate([
            'find' => ['required', 'string', 'min:1', 'max:5000'],
            'replace' => ['present', 'string', 'max:5000'],
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['required', 'string', 'max:128'],
            'case_sensitive' => ['sometimes', 'boolean'],
            'ignore_slugs' => ['sometimes', 'boolean'],
            'confirmation' => ['required', 'string'],
        ]);

        $expected = $this->searchReplace->confirmPhrase();
        if (! hash_equals($expected, (string) $validated['confirmation'])) {
            return response()->json([
                'message' => 'Confirmation phrase does not match.',
                'errors' => [
                    'confirmation' => ['You must type '.$expected.' exactly to apply replacements.'],
                ],
            ], 422);
        }

        try {
            $result = $this->searchReplace->apply(
                $validated['tables'],
                $validated['find'],
                (string) $validated['replace'],
                (bool) ($validated['case_sensitive'] ?? false),
                (bool) ($validated['ignore_slugs'] ?? true),
            );
        } catch (Throwable $e) {
            return response()->json([
                'message' => $this->safeMessage($e, 'Search/replace failed.'),
            ], 500);
        }

        return response()->json([
            'message' => 'Replace complete.',
            'data' => $result,
        ]);
    }

    private function safeMessage(Throwable $e, string $fallback): string
    {
        if (app()->hasDebugModeEnabled() && $e->getMessage() !== '') {
            return $e->getMessage();
        }

        return $fallback;
    }
}
