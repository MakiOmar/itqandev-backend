<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicMarketingShellService;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single public payload for marketing layout SSR (branding + menu + services).
 */
class PublicShellController extends Controller
{
    public function show(Request $request, PublicMarketingShellService $shell): JsonResponse
    {
        $locale = $shell->resolveLocaleFromRequest($request);
        $present = TranslatableContentPresenter::requestedPresentationLocale($request) ?? $locale;

        return response()->json([
            'success' => true,
            'data' => $shell->build(
                $locale,
                $present,
                $request->query('path') ?: $request->headers->get('X-Document-Path'),
                $request,
                $request->query('theme_context') ?: $request->headers->get('X-Theme-Context')
            ),
        ]);
    }
}
