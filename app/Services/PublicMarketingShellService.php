<?php

namespace App\Services;

use App\Http\Controllers\Api\SettingsController;
use App\Models\Service;
use App\Services\Appearance\ChromeLayoutResolver;
use App\Services\Appearance\HomepageBuilderService;
use App\Support\FeatureModules;
use App\Support\PublishedServicesQuery;
use App\Support\SeoMetaPresenter;
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
        $version = (int) Cache::get(self::SHELL_VERSION_KEY, 1);
        Cache::forever(self::SHELL_VERSION_KEY, $version + 1);

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
     *   theme_context: string|null,
     *   theme_template_id: int|null
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
        $cacheKey = 'public:shell:v'.self::shellVersion().':'.$locale.':loc:'.$present.':chrome:'.$pathKey.':ctx:'.$ctxKey;

        /** @var array{site_meta: array<string, mixed>, menu: array{slug: string, locale: string, items: list<mixed>}, services: list<array<string, mixed>>, homepage_sections: list<array<string, mixed>>, header: array{sections: list<array<string, mixed>>}, footer: array{sections: list<array<string, mixed>>}, theme_body: array{sections: list<array<string, mixed>>}|null, theme_context: string|null, theme_template_id: int|null} $payload */
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
                    ? $this->buildServicesPayload($present)
                    : [],
                'homepage_sections' => $homepageSections,
                'header' => $chrome['header'],
                'footer' => $chrome['footer'],
                'theme_body' => $themeBody,
                'theme_context' => $chrome['context'] ?? null,
                'theme_template_id' => $chrome['theme_template_id'] ?? null,
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

    /**
     * @return list<array<string, mixed>>
     */
    private function buildServicesPayload(string $present): array
    {
        $cacheKey = 'public:services:loc:'.($present !== '' ? $present : 'none');

        $services = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($present) {
            return PublishedServicesQuery::fetchPublished($present !== '' ? $present : null);
        });

        return $services->map(function (Service $s) use ($present) {
            $primary = SiteLanguages::primaryLocaleForContent($s->content_locale);
            $picked = SeoMetaPresenter::pickLocalized(
                $s->relationLoaded('seoMetas') ? $s->seoMetas : null,
                $present !== '' ? $present : null,
                $primary
            );

            return [
                'id' => (string) $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'shortDescription' => $s->short_description ?? '',
                'description' => $s->description ?? '',
                'process' => is_array($s->process) ? $s->process : [],
                'deliverables' => is_array($s->deliverables) ? $s->deliverables : [],
                'icon' => $s->icon,
                'seo_meta' => SeoMetaPresenter::toPublicSnippet($picked),
            ];
        })->values()->all();
    }
}
