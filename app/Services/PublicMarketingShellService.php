<?php

namespace App\Services;

use App\Http\Controllers\Api\SettingsController;
use App\Models\Menu;
use App\Services\Appearance\ChromeLayoutResolver;
use App\Services\Appearance\HomepageBuilderService;
use App\Support\BuilderContentVersions;
use App\Support\CacheKey;
use App\Support\FeatureModules;
use App\Support\PublicCache;
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

        $resolver = app(ChromeLayoutResolver::class);
        $forcedContext = $resolver->knownContext($forcedContext);
        $path = $documentPath !== null && trim($documentPath) !== '' ? $resolver->canonicalPath($documentPath) : null;
        // Embeds the primary menu, service cards and homepage/chrome widgets.
        $keyBase = 'public:shell:v'.self::shellVersion().':'.BuilderContentVersions::token([Menu::class])
            .':'.$locale.':loc:'.$present.':ctx:'.($forcedContext ?? 'auto').':chrome:';
        $buildFor = fn (?string $p, ?string $ctx) => $this->assemble(
            $locale,
            $present,
            $resolver->resolveForDocumentPath($p, $present, $request, $ctx)
        );

        if ($path === null) {
            return Cache::remember($keyBase.'site', self::CACHE_SECONDS, fn () => $buildFor(null, $forcedContext));
        }

        // Paths naming no published document share one not-found payload, so hostile paths
        // only leave a short-lived marker instead of a full shell entry each.
        $payload = PublicCache::rememberOrMissing($keyBase.sha1($path), self::CACHE_SECONDS, function () use ($resolver, $path, $present, $request, $forcedContext, $locale) {
            $chrome = $resolver->resolveForDocumentPath($path, $present, $request, $forcedContext);

            return $chrome['document_found'] ? $this->assemble($locale, $present, $chrome) : null;
        });

        return $payload ?? Cache::remember(
            $keyBase.'not-found',
            self::CACHE_SECONDS,
            fn () => $buildFor(null, 'not_found')
        );
    }

    /**
     * @param  array<string, mixed>  $chrome  `ChromeLayoutResolver::resolveForDocumentPath()` result
     * @return array<string, mixed>
     */
    private function assemble(string $locale, string $present, array $chrome): array
    {
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
            'homepage_sections' => app(HomepageBuilderService::class)->presentPublic($present),
            'header' => $chrome['header'],
            'footer' => $chrome['footer'],
            'theme_body' => $chrome['theme_body'] ?? null,
            'theme_body_mode' => $chrome['theme_body_mode'] ?? null,
            'theme_context' => $chrome['context'] ?? null,
            'theme_template_id' => $chrome['theme_template_id'] ?? null,
            'overlays' => \App\Services\Appearance\ChromeLayoutSupport::delayedPublicOverlays(),
        ];
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
