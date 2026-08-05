<?php

namespace App\Services\Appearance;

use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;

/**
 * Footer appearance builder using the CMS page-layout document shape.
 * Migrates legacy zone-based `footer_builder` documents on read.
 */
final class FooterBuilderService
{
    public const SETTINGS_KEY = 'footer_builder';

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function defaultDocument(): array
    {
        $sections = [
            ChromeLayoutSupport::makeBand('band_footer_main', [
                [
                    'id' => 'col_footer_brand',
                    'span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 3],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('footer_brand', [], 'kit_footer_brand'),
                    ],
                ],
                [
                    'id' => 'col_footer_links',
                    'span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 3],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('footer_links', [], 'kit_footer_links'),
                    ],
                ],
                [
                    'id' => 'col_footer_contact',
                    'span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 3],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('footer_contact', [], 'kit_footer_contact'),
                        ChromeLayoutSupport::makeKitBlock('footer_social', ['title' => ''], 'kit_footer_social'),
                    ],
                ],
                [
                    'id' => 'col_footer_cta',
                    'span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 3],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('footer_cta', [], 'kit_footer_cta'),
                    ],
                ],
            ], 'boxed', 'tablet'),
            ChromeLayoutSupport::makeBand('band_footer_bottom', [
                [
                    'id' => 'col_footer_copyright',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('footer_copyright', [], 'kit_footer_copyright'),
                    ],
                ],
            ], 'boxed', 'none'),
        ];

        return ChromeLayoutSupport::normalizeDocument(['sections' => $sections]);
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function loadAdminDocument(): array
    {
        $stored = ProjectSettingsStore::load();
        $raw = $stored[self::SETTINGS_KEY] ?? null;
        if (! is_array($raw)) {
            return $this->defaultDocument();
        }

        if (isset($raw['zones']) && is_array($raw['zones']) && (! isset($raw['sections']) || $raw['sections'] === [])) {
            $migrated = ChromeLayoutSupport::migrateLegacyFooterZones($raw);
            if ($migrated !== null) {
                return $migrated;
            }
        }

        if (! isset($raw['sections']) || ! is_array($raw['sections']) || $raw['sections'] === []) {
            return $this->defaultDocument();
        }

        return ChromeLayoutSupport::normalizeDocument($raw);
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function presentPublic(?string $locale = null): array
    {
        return ChromeLayoutSupport::presentPublic($this->loadAdminDocument(), $locale);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{sections: list<array<string, mixed>>}
     */
    public function save(array $input): array
    {
        $normalized = ChromeLayoutSupport::normalizeDocument($input);
        if (($normalized['sections'] ?? []) === []) {
            $normalized = $this->defaultDocument();
        }
        ProjectSettingsStore::merge([self::SETTINGS_KEY => $normalized]);
        MarketingSettingsCache::forgetAll();

        return $normalized;
    }
}
