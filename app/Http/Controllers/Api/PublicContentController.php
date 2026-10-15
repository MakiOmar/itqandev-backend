<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentTypes\PublicContentService;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketing reads for content types rendered by the website's registry-driven `/{prefix}/{slug}` route.
 */
class PublicContentController extends Controller
{
    public function __construct(private readonly PublicContentService $content) {}

    public function show(Request $request, string $prefix, string $slug): JsonResponse
    {
        $locale = TranslatableContentPresenter::requestedPresentationLocale($request);
        $single = $this->content->single($prefix, $slug, $locale);
        if ($single === null) {
            abort(404);
        }

        return response()->json(['success' => true, 'data' => $single]);
    }

    public function sitemap(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->content->addonSitemapPaths()]);
    }
}
