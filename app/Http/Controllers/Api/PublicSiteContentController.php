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
        $locale = TranslatableContentPresenter::requestedPresentationLocale($request)
            ?? $this->enabledLocale((string) $request->query('locale', ''));

        $cacheKey = 'public:site-content:loc:'.$locale;

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

    /** Unknown `?locale=` values fall back to the default so they cannot mint cache keys. */
    private function enabledLocale(string $raw): string
    {
        $locale = strtolower(trim($raw));
        $codes = array_map(fn ($row) => strtolower((string) ($row['code'] ?? '')), SiteLanguages::all());

        return $locale !== '' && in_array($locale, $codes, true) ? $locale : SiteLanguages::defaultCode();
    }
}
