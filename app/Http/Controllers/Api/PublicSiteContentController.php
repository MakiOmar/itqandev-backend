<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SiteSettingsPresenter;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicSiteContentController extends Controller
{
    private const CACHE_SECONDS = 300;

    public function show(Request $request): JsonResponse
    {
        $locale = TranslatableContentPresenter::requestedPresentationLocale($request);
        if ($locale === null) {
            $queryLocale = strtolower(trim((string) $request->query('locale', '')));
            $locale = $queryLocale !== '' ? $queryLocale : SiteLanguages::defaultCode();
        }

        $cacheKey = 'public:site-content:loc:'.strtolower(trim($locale));

        $data = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($locale) {
            /** @var SettingsController $settingsController */
            $settingsController = app(SettingsController::class);

            return SiteSettingsPresenter::marketingContentPayload(
                $settingsController->localizedSettings($locale)
            );
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
