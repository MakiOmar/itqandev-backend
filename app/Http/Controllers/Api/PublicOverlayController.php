<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChromeLayout;
use App\Services\Appearance\ChromeLayoutService;
use App\Support\FeatureModules;
use Illuminate\Http\JsonResponse;

/**
 * Published overlay documents for marketing (button / delayed CTA).
 */
class PublicOverlayController extends Controller
{
    public function show(int $id, ChromeLayoutService $layouts): JsonResponse
    {
        if (! FeatureModules::enabled('overlays')) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $layout = ChromeLayout::query()
            ->kind(ChromeLayout::KIND_OVERLAY)
            ->published()
            ->find($id);

        if ($layout === null) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $presented = $layouts->presentById((int) $layout->id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $layout->id,
                'name' => $layout->name,
                'sections' => $presented['sections'] ?? [],
                'overlay' => $presented['overlay'] ?? null,
                'css' => $presented['css'] ?? '',
                'css_hash' => $presented['css_hash'] ?? null,
            ],
        ]);
    }
}
