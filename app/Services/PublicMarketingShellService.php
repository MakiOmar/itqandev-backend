<?php

namespace App\Services;

use App\Http\Controllers\Api\SettingsController;
use App\Models\Testimonial;
use App\Services\Appearance\ChromeLayoutResolver;
use App\Services\Appearance\HomepageBuilderService;
use App\Support\CacheKey;
use App\Support\FeatureModules;
use App\Support\PublishedServicesQuery;
use App\Support\SiteLanguages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates public marketing shell data (branding, primary menu, services) in one pass.
 */
final class PublicMarketingShellService
{
    private const CACHE_SECONDS = 300;

    private const SHELL_VERSION_KEY = 'public:shell:version';

    /** The shell embeds the primary menu, service cards and homepage/chrome widgets fed by these models. */
    private const CACHE_DEPENDENCIES = [...PublicMenuResolver::CACHE_DEPENDENCIES, Testimonial::class];

    /** Bust cached shell payloads after typography or font library changes. */
    public static function forgetShellCaches(): void
    {
        CacheKey::incrementVersion(self::SHELL_VERSION_KEY);

        $locales = array_unique(array_merge(
            [SiteLanguages::defaultCode(), 'en', 'ar'],
            array_map(
                fn ($row) => strtolower((string) ($row['code'] ?? '')),
                SiteLanguages::all()
            ),
        ));

        foreach ($locales as $locale) {
            $locale = strtolower(trim($locale));
            if ($locale === '') {
                continue;
            }
            foreach ($locales as $present) {
                $present = strtolower(trim($present));
                if ($present === '') {
                    continue;
                }
                // Legacy keys (pre path-aware chrome)
                Cache::forget('public:shell:'.$locale.':loc:'.$present);
                Cache::forget('public:site-content:loc:'.$present);
            }
            Cache::forget('public:site-content:loc:'.$locale);
        }
    }

    private static function shellVersion(): int
    {
        return (int) Cache::get(self::SHELL_VERSION_KEY, 1);
    }

    /**
     * @return array{
     *   site_meta: array<string, mixed>,
     *   menu: array{slug: string, locale: string, items: list<mixed>},
     *   services: list<array<string, mixed>>,
     *   homepage_sections: list<array<string, mixed>>,
     *   header: array{sections: list<array<string, mixed>>},
     *   footer: array{sections: list<array<string, mixed>>},
     *   theme_body: array{sections: list<array<string, mixed>>}|null,
     *   theme_body_mode: 'replace'|'wrap'|null,
     *   theme_context: string|null,
     *   theme_template_id: int|null,
     *   overlays: list<array{id: int, delay_ms: int, once: bool, sitewide: bool}>
     * }
     */
    public function build(
        string $locale,
        ?string $presentationLocale = null,
        ?string $documentPath = null,
        ?Request $request = null,
        ?string $forcedContext = null,
    ): array {
        $locale = $this->normalizeLocale($locale);
        $present = $presentationLocale !== null && $presentationLocale !== ''
            ? strtolower(trim($presentationLocale))
            : $locale;

        $pathKey = $documentPath !== null && trim($documentPath) !== ''
            ? sha1(strtolower(trim($documentPath)))
            : 'site';
        $ctxKey = $forcedContext !== null && trim($forcedContext) !== ''
            ? sha1(strtolower(trim($forcedContext)))
            : 'auto';
        $cacheKey = 'public:shell:v'.self::shellVersion().':'.CacheKey::versionedMany(self::CACHE_DEPENDENCIES)
            .':'.$locale.':loc:'.$present.':chrome:'.$pathKey.':ctx:'.$ctxKey;

        /** @var array{site_meta: array<string, mixed>, menu: array{slug: string, locale: string, items: list<mixed>}, services: list<array<string, mixed>>, homepage_sections: list<array<string, mixed>>, header: array{sections: list<array<string, mixed>>}, footer: array{sections: list<array<string, mixed>>}, theme_body: array{sections: list<array<string, mixed>>}|null, theme_body_mode: string|null, theme_context: string|null, theme_template_id: int|null} $payload */
        $payload = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($locale, $present, $documentPath, $request, $forcedContext) {
            $homepage = app(HomepageBuilderService::class);
            $chrome = app(ChromeLayoutResolver::class)->resolveForDocumentPath(
                $documentPath,
                $present,
                $request,
                $forcedContext
            );

            $homepageSections = $homepage->presentPublic($present);
            $themeBody = $chrome['theme_body'] ?? null;

            return [
                'site_meta' => $this->buildSiteMeta($present),
                'menu' => [
                    'slug' => 'primary',
                    'locale' => $locale,
                    'items' => PublicMenuResolver::resolvePublishedTree('primary', $locale),
                ],
                'services' => FeatureModules::enabled('services')
                    ? PublishedServicesQuery::publicPayload($present)
                    : [],
                'homepage_sections' => $homepageSections,
                'header' => $chrome['header'],
                'footer' => $chrome['footer'],
                'theme_body' => $themeBody,
                'theme_body_mode' => $chrome['theme_body_mode'] ?? null,
                'theme_context' => $chrome['context'] ?? null,
                'theme_template_id' => $chrome['theme_template_id'] ?? null,
                'overlays' => \App\Services\Appearance\ChromeLayoutSupport::delayedPublicOverlays(),
            ];
        });

        return $payload;
    }

    public function resolveLocaleFromRequest(Request $request): string
    {
        $locale = strtolower(trim((string) $request->query('locale', SiteLanguages::defaultCode())));
        $codes = array_map(
            fn ($r) => strtolower((string) ($r['code'] ?? '')),
            SiteLanguages::all()
        );
        if ($codes !== [] && ! in_array($locale, $codes, true)) {
            return SiteLanguages::defaultCode();
        }

        return $this->normalizeLocale($locale);
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return $locale !== '' ? $locale : SiteLanguages::defaultCode();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSiteMeta(string $presentationLocale): array
    {
        /** @var SettingsController $settings */
        $settings = app(SettingsController::class);

        return $settings->buildPublicMetaData($presentationLocale);
    }
}
