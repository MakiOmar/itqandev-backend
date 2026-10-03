<?php

namespace App\Services\Appearance;

use App\Models\ChromeLayout;
use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use Illuminate\Support\Facades\Schema;

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
                        ChromeLayoutSupport::makeKitBlock('header_mobile_menu', [], 'kit_header_mobile_menu'),
                    ],
                ],
            ], 'full', 'none'),
        ];
        $sections[0] = $this->withDefaultBarStyle($sections[0]);

        return ChromeLayoutSupport::normalizeDocument(['sections' => $sections]);
    }

    /**
     * Starting look of the default header bar, stored on the band/row so every part stays editable
     * in the header builder (the public shell adds no background, border, padding or width).
     *
     * @param  array<string, mixed>  $band
     * @return array<string, mixed>
     */
    private function withDefaultBarStyle(array $band): array
    {
        $band['settings']['background'] = [
            'type' => 'color',
            'color' => '#ffffffe6',
            'backdrop_blur' => 12,
            'dark' => ['color' => '#0f172ae6'],
        ];
        $hairline = static fn (string $color): array => ['color' => $color, 'h' => 0, 'v' => 1, 'blur' => 0, 'spread' => 0, 'inset' => false];
        $band['styles'] = [
            'desktop' => ['box_shadow' => $hairline('#e2e8f0cc')],
            'dark' => ['box_shadow' => $hairline('#334155cc')],
        ];

        $padding = static fn (int $x): array => ['top' => 12, 'right' => $x, 'bottom' => 12, 'left' => $x, 'unit' => 'px', 'linked' => false];
        $band['rows'][0]['styles'] = [
            'desktop' => ['max_width' => ['value' => 72, 'unit' => 'rem'], 'align' => 'center', 'padding' => $padding(32)],
            'tablet' => ['padding' => $padding(24)],
            'mobile' => ['padding' => $padding(16)],
        ];

        return $band;
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function loadAdminDocument(): array
    {
        $siteDefault = $this->siteDefaultLayout();
        if ($siteDefault !== null) {
            $document = is_array($siteDefault->document) ? $siteDefault->document : [];

            return ChromeLayoutSupport::normalizeDocument($document !== [] ? $document : $this->defaultDocument());
        }

        $stored = ProjectSettingsStore::load();
        $raw = $stored[self::SETTINGS_KEY] ?? null;
        if (! is_array($raw) || ! isset($raw['sections']) || ! is_array($raw['sections']) || $raw['sections'] === []) {
            return $this->defaultDocument();
        }

        return ChromeLayoutSupport::normalizeDocument($raw);
    }

    /**
     * Site-wide public header (homepage type defaults → site default).
     *
     * @return array{sections: list<array<string, mixed>>}
     */
    public function presentPublic(?string $locale = null): array
    {
        if (Schema::hasTable('chrome_layouts')) {
            return app(ChromeLayoutResolver::class)->resolve(
                ChromeLayout::KIND_HEADER,
                'homepage',
                null,
                $locale
            );
        }

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

        $siteDefault = $this->siteDefaultLayout();
        if ($siteDefault !== null) {
            app(ChromeLayoutService::class)->update($siteDefault, [
                'sections' => $normalized['sections'],
            ]);
            // Keep settings key for one-release fallback.
            ProjectSettingsStore::merge([self::SETTINGS_KEY => $normalized]);

            return $normalized;
        }

        ProjectSettingsStore::merge([self::SETTINGS_KEY => $normalized]);
        MarketingSettingsCache::forgetAll();

        return $normalized;
    }

    private function siteDefaultLayout(): ?ChromeLayout
    {
        if (! Schema::hasTable('chrome_layouts')) {
            return null;
        }

        return app(ChromeLayoutService::class)->findSiteDefault(ChromeLayout::KIND_HEADER);
    }
}
