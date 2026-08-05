<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Services\Appearance\FooterBuilderService;
use App\Services\Appearance\HeaderBuilderService;
use App\Services\Appearance\HomepageBuilderService;
use App\Services\Appearance\HomepageSectionRegistry;
use App\Services\Appearance\KitRegistry;
use App\Services\Appearance\WidgetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppearanceController extends Controller
{
    public function __construct(
        private readonly HomepageBuilderService $homepage,
        private readonly FooterBuilderService $footer,
        private readonly HeaderBuilderService $header,
    ) {}

    public function registries(): JsonResponse
    {
        $this->authorize('manageSettings');

        return response()->json([
            'success' => true,
            'data' => [
                'widgets' => WidgetRegistry::forAdmin(),
                'kits' => KitRegistry::forAdmin(),
                'homepage_sections' => HomepageSectionRegistry::forAdmin(),
                'form_fields' => \App\Services\Forms\FormFieldRegistry::forAdmin(),
                'form_actions' => \App\Services\Forms\FormActionRegistry::forAdmin(),
            ],
        ]);
    }

    public function showHomepage(): JsonResponse
    {
        $this->authorize('manageSettings');

        return response()->json([
            'success' => true,
            'data' => $this->homepage->loadAdminDocument(),
        ]);
    }

    public function updateHomepage(Request $request): JsonResponse
    {
        $this->authorize('manageSettings');

        $validated = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'nullable|string|max:64',
            'sections.*.type' => 'required|string|max:64',
            'sections.*.enabled' => 'sometimes|boolean',
            'sections.*.layout_width' => 'nullable|string|in:boxed,full',
            'sections.*.settings' => 'nullable|array',
        ]);

        $saved = $this->homepage->save($validated);
        ActivityLogService::record('appearance.homepage.updated', null, [
            'sections_count' => count($saved['sections'] ?? []),
        ], $request);

        return response()->json([
            'success' => true,
            'data' => $saved,
            'message' => 'Homepage layout saved.',
        ]);
    }

    public function showHeader(): JsonResponse
    {
        $this->authorize('manageSettings');

        return response()->json([
            'success' => true,
            'data' => $this->header->loadAdminDocument(),
        ]);
    }

    public function updateHeader(Request $request): JsonResponse
    {
        $this->authorize('manageSettings');

        $validated = $request->validate([
            'sections' => 'required|array',
        ]);

        $saved = $this->header->save($validated);
        ActivityLogService::record('appearance.header.updated', null, [
            'sections_count' => count($saved['sections'] ?? []),
        ], $request);

        return response()->json([
            'success' => true,
            'data' => $saved,
            'message' => 'Header layout saved.',
        ]);
    }

    public function showFooter(): JsonResponse
    {
        $this->authorize('manageSettings');

        return response()->json([
            'success' => true,
            'data' => $this->footer->loadAdminDocument(),
        ]);
    }

    public function updateFooter(Request $request): JsonResponse
    {
        $this->authorize('manageSettings');

        $validated = $request->validate([
            'sections' => 'required|array',
        ]);

        $saved = $this->footer->save($validated);
        ActivityLogService::record('appearance.footer.updated', null, [
            'sections_count' => count($saved['sections'] ?? []),
        ], $request);

        return response()->json([
            'success' => true,
            'data' => $saved,
            'message' => 'Footer layout saved.',
        ]);
    }
}
