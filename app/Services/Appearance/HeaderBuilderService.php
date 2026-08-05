<?php

namespace App\Services\Appearance;

use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;

final class HeaderBuilderService
{
    public const SETTINGS_KEY = 'header_builder';

    /**
     * Default header: brand | menu | spacer | cta + actions.
     *
     * @return array{sections: list<array<string, mixed>>}
     */
    public function defaultDocument(): array
    {
        $sections = [
            ChromeLayoutSupport::makeBand('band_header_main', [
                [
                    'id' => 'col_header_brand',
                    'span' => ['mobile' => 6, 'tablet' => 3, 'desktop' => 2],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('header_brand', [], 'kit_header_brand'),
                    ],
                ],
                [
                    'id' => 'col_header_menu',
                    'span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 5],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('header_menu', [
                            'menu_slug' => 'primary',
                            'show_children_mobile' => true,
                        ], 'kit_header_menu'),
                    ],
                ],
                [
                    'id' => 'col_header_spacer',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 1],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('header_spacer', [], 'kit_header_spacer'),
                    ],
                ],
                [
                    'id' => 'col_header_cta',
                    'span' => ['mobile' => 6, 'tablet' => 3, 'desktop' => 2],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('header_cta', [], 'kit_header_cta'),
                    ],
                ],
                [
                    'id' => 'col_header_actions',
                    'span' => ['mobile' => 6, 'tablet' => 3, 'desktop' => 2],
                    'blocks' => [
                        ChromeLayoutSupport::makeKitBlock('header_actions', [], 'kit_header_actions'),
                    ],
                ],
            ], 'full', 'none'),
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
        if (! is_array($raw) || ! isset($raw['sections']) || ! is_array($raw['sections']) || $raw['sections'] === []) {
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
