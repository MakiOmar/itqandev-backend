<?php

namespace App\Services;

use App\Http\Controllers\Api\SettingsController;
use App\Models\Service;
use App\Support\FeatureModules;
use App\Support\SeoMetaPresenter;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates public marketing shell data (branding, primary menu, services) in one pass.
 */
final class PublicMarketingShellService
{
    private const CACHE_SECONDS = 300;

    /** Bust cached shell payloads after typography or font library changes. */
    public static function forgetShellCaches(): void
    {
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
                Cache::forget('public:shell:'.$locale.':loc:'.$present);
                Cache::forget('public:site-content:loc:'.$present);
            }
            Cache::forget('public:site-content:loc:'.$locale);
        }
    }

    /**
     * @return array{
     *   site_meta: array<string, mixed>,
     *   menu: array{slug: string, locale: string, items: list<mixed>},
     *   services: list<array<string, mixed>>
     * }
     */
    public function build(string $locale, ?string $presentationLocale = null): array
    {
        $locale = $this->normalizeLocale($locale);
        $present = $presentationLocale !== null && $presentationLocale !== ''
            ? strtolower(trim($presentationLocale))
            : $locale;

        $cacheKey = 'public:shell:'.$locale.':loc:'.$present;

        /** @var array{site_meta: array<string, mixed>, menu: array{slug: string, locale: string, items: list<mixed>}, services: list<array<string, mixed>>} $payload */
        $payload = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($locale, $present) {
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
        $siteDefaultLocale = SiteLanguages::defaultCode();

        $services = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($present, $siteDefaultLocale) {
            $list = Service::query()
                ->with(['translations', 'seoMetas'])
                ->where('is_published', true)
                ->when($present !== '', function ($query) use ($present, $siteDefaultLocale) {
                    $query->where(function ($q) use ($present, $siteDefaultLocale) {
                        $q->where('content_locale', $present);
                        if ($present === $siteDefaultLocale) {
                            $q->orWhereNull('content_locale');
                        }
                        $q->orWhereHas('translations', function ($tq) use ($present) {
                            $tq->where('locale', $present);
                        });
                    });
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
            if ($present !== '') {
                $list->each(function (Service $service) use ($present) {
                    TranslatableContentPresenter::applyService($service, $present);
                })->filter(function (Service $service) use ($present) {
                    return TranslatableContentPresenter::hasServiceContentForLocale($service, $present);
                })->values();
            }

            return $list;
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
