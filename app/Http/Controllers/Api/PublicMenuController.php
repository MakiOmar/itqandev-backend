<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicMenuResolver;
use App\Support\SiteLanguages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicMenuController extends Controller
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' || ! preg_match('/^[a-z0-9_-]+$/', $slug)) {
            return response()->json([
                'success' => true,
                'data' => ['items' => []],
            ]);
        }

        $locale = strtolower(trim((string) $request->query('locale', SiteLanguages::defaultCode())));
        $codes = array_map(fn ($r) => strtolower((string) ($r['code'] ?? '')), SiteLanguages::all());
        if ($codes !== [] && ! in_array($locale, $codes, true)) {
            $locale = SiteLanguages::defaultCode();
        }

        $items = PublicMenuResolver::resolvePublishedTree($slug, $locale);

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $slug,
                'locale' => $locale,
                'items' => $items,
            ],
        ]);
    }
}
