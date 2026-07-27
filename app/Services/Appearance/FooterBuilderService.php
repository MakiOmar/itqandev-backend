<?php

namespace App\Services\Appearance;

use App\Services\PublicMenuResolver;
use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use App\Support\SiteLanguages;
use Illuminate\Support\Str;

final class FooterBuilderService
{
    public const SETTINGS_KEY = 'footer_builder';

    public const ZONES = ['top', 'main', 'bottom'];

    private const MAX_COLUMNS_PER_ZONE = 6;

    private const MAX_BLOCKS_PER_COLUMN = 12;

    private const MAX_LINKS_PER_BLOCK = 24;

    /**
     * Soft-landing default: hardcoded mode + main zone mirroring today's 4 columns.
     *
     * @return array<string, mixed>
     */
    public function defaultDocument(): array
    {
        return [
            'mode' => 'hardcoded',
            'zones' => [
                'top' => ['enabled' => false, 'columns' => []],
                'main' => [
                    'enabled' => true,
                    'columns' => [
                        $this->makeColumn('col_brand', 3, [
                            $this->makeBlock('brand'),
                        ]),
                        $this->makeColumn('col_links', 3, [
                            $this->makeBlock('links', [
                                'title' => 'Quick links',
                                'links' => [
                                    ['id' => 'lnk_services', 'label' => 'Services', 'url' => '/services'],
                                    ['id' => 'lnk_work', 'label' => 'Work', 'url' => '/work'],
                                    ['id' => 'lnk_about', 'label' => 'About', 'url' => '/about'],
                                    ['id' => 'lnk_pricing', 'label' => 'Pricing', 'url' => '/pricing'],
                                    ['id' => 'lnk_blog', 'label' => 'Blog', 'url' => '/blog'],
                                    ['id' => 'lnk_contact', 'label' => 'Contact', 'url' => '/contact'],
                                ],
                            ]),
                        ]),
                        $this->makeColumn('col_contact', 3, [
                            $this->makeBlock('contact'),
                            $this->makeBlock('social', ['title' => '']),
                        ]),
                        $this->makeColumn('col_cta', 3, [
                            $this->makeBlock('cta'),
                        ]),
                    ],
                ],
                'bottom' => [
                    'enabled' => true,
                    'columns' => [
                        $this->makeColumn('col_copyright', 12, [
                            $this->makeBlock('rich_text', [
                                'title' => '',
                                'body' => '© {year} {brand}. All rights reserved.',
                            ]),
                        ]),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadAdminDocument(): array
    {
        $stored = ProjectSettingsStore::load();
        $raw = $stored[self::SETTINGS_KEY] ?? null;
        if (! is_array($raw)) {
            return $this->defaultDocument();
        }

        return $this->normalizeDocument($raw);
    }

    /**
     * Public payload: mode + enabled zones with enabled blocks only.
     * Menu blocks include resolved `items` for SSR.
     *
     * @return array<string, mixed>
     */
    public function presentPublic(?string $locale = null): array
    {
        $doc = $this->loadAdminDocument();
        $mode = (string) ($doc['mode'] ?? 'hardcoded');
        if ($mode !== 'builder') {
            return ['mode' => 'hardcoded'];
        }

        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : SiteLanguages::defaultCode();

        $zones = [];
        foreach (self::ZONES as $zoneKey) {
            $zone = $doc['zones'][$zoneKey] ?? null;
            if (! is_array($zone) || ! ($zone['enabled'] ?? false)) {
                continue;
            }
            $columns = [];
            foreach ($zone['columns'] ?? [] as $col) {
                if (! is_array($col)) {
                    continue;
                }
                $blocks = [];
                foreach ($col['blocks'] ?? [] as $block) {
                    if (! is_array($block) || ! ($block['enabled'] ?? false)) {
                        continue;
                    }
                    $type = (string) ($block['type'] ?? '');
                    if (! FooterBlockRegistry::has($type)) {
                        continue;
                    }
                    $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
                    $settings = AppearanceLocalizedSettings::resolveForLocale(
                        $settings,
                        $locale,
                        SiteLanguages::defaultCode(),
                        FooterBlockRegistry::translatableKeys($type),
                    );
                    if ($type === 'menu') {
                        $slug = strtolower(trim((string) ($settings['menu_slug'] ?? 'primary')));
                        if ($slug === '') {
                            $slug = 'primary';
                        }
                        $settings['menu_slug'] = $slug;
                        $settings['items'] = PublicMenuResolver::resolvePublishedTree($slug, $locale);
                    }
                    $blocks[] = [
                        'id' => (string) $block['id'],
                        'type' => $type,
                        'settings' => $settings,
                    ];
                }
                if ($blocks === []) {
                    continue;
                }
                $columns[] = [
                    'id' => (string) $col['id'],
                    'span' => (int) ($col['span'] ?? 3),
                    'blocks' => $blocks,
                ];
            }
            if ($columns === []) {
                continue;
            }
            $zones[$zoneKey] = [
                'enabled' => true,
                'columns' => $columns,
            ];
        }

        return [
            'mode' => 'builder',
            'zones' => $zones,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $normalized = $this->normalizeDocument($input);
        ProjectSettingsStore::merge([self::SETTINGS_KEY => $normalized]);
        MarketingSettingsCache::forgetAll();

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeDocument(array $input): array
    {
        $mode = strtolower(trim((string) ($input['mode'] ?? 'hardcoded')));
        if (! in_array($mode, ['hardcoded', 'builder'], true)) {
            $mode = 'hardcoded';
        }

        $rawZones = is_array($input['zones'] ?? null) ? $input['zones'] : [];
        $zones = [];
        foreach (self::ZONES as $zoneKey) {
            $raw = is_array($rawZones[$zoneKey] ?? null) ? $rawZones[$zoneKey] : [];
            $zones[$zoneKey] = $this->normalizeZone($raw);
        }

        // If zones empty after normalize, fall back to defaults but keep chosen mode.
        $hasAnyColumn = false;
        foreach ($zones as $z) {
            if (($z['columns'] ?? []) !== []) {
                $hasAnyColumn = true;
                break;
            }
        }
        if (! $hasAnyColumn) {
            $defaults = $this->defaultDocument();
            $zones = $defaults['zones'];
        }

        return [
            'mode' => $mode,
            'zones' => $zones,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{enabled: bool, columns: list<array<string, mixed>>}
     */
    private function normalizeZone(array $raw): array
    {
        $columns = [];
        $rawColumns = is_array($raw['columns'] ?? null) ? $raw['columns'] : [];
        foreach ($rawColumns as $col) {
            if (! is_array($col)) {
                continue;
            }
            if (count($columns) >= self::MAX_COLUMNS_PER_ZONE) {
                break;
            }
            $normalized = $this->normalizeColumn($col);
            if ($normalized !== null) {
                $columns[] = $normalized;
            }
        }

        return [
            'enabled' => filter_var($raw['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'columns' => $columns,
        ];
    }

    /**
     * @param  array<string, mixed>  $col
     * @return array<string, mixed>|null
     */
    private function normalizeColumn(array $col): ?array
    {
        $id = trim((string) ($col['id'] ?? ''));
        if ($id === '') {
            $id = 'col_'.Str::lower(Str::random(10));
        }
        $span = (int) ($col['span'] ?? 3);
        $span = max(1, min(12, $span));

        $blocks = [];
        $counts = [];
        $rawBlocks = is_array($col['blocks'] ?? null) ? $col['blocks'] : [];
        foreach ($rawBlocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (count($blocks) >= self::MAX_BLOCKS_PER_COLUMN) {
                break;
            }
            $type = strtolower(trim((string) ($block['type'] ?? '')));
            if (! FooterBlockRegistry::has($type)) {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
            $max = FooterBlockRegistry::maxInstances($type);
            if ($max !== null && $counts[$type] > $max) {
                continue;
            }
            $blockId = trim((string) ($block['id'] ?? ''));
            if ($blockId === '') {
                $blockId = 'blk_'.Str::lower(Str::random(10));
            }
            $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
            $blocks[] = [
                'id' => $blockId,
                'type' => $type,
                'enabled' => filter_var($block['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'settings' => $this->normalizeBlockSettings($type, $settings),
            ];
        }

        return [
            'id' => $id,
            'span' => $span,
            'blocks' => $blocks,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizeBlockSettings(string $type, array $settings): array
    {
        $merged = AppearanceLocalizedSettings::normalize(
            $settings,
            FooterBlockRegistry::defaultSettings($type),
            FooterBlockRegistry::translatableKeys($type),
        );

        if ($type === 'links') {
            $links = [];
            $rawLinks = is_array($merged['links'] ?? null) ? $merged['links'] : [];
            foreach ($rawLinks as $link) {
                if (! is_array($link)) {
                    continue;
                }
                if (count($links) >= self::MAX_LINKS_PER_BLOCK) {
                    break;
                }
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));
                if ($label === '' || $url === '') {
                    continue;
                }
                $linkId = trim((string) ($link['id'] ?? ''));
                if ($linkId === '') {
                    $linkId = 'lnk_'.Str::lower(Str::random(8));
                }
                $links[] = [
                    'id' => $linkId,
                    'label' => $label,
                    'url' => $url,
                ];
            }
            $merged['links'] = $links;
        }

        if ($type === 'menu') {
            $slug = strtolower(trim((string) ($merged['menu_slug'] ?? 'primary')));
            $merged['menu_slug'] = $slug !== '' ? $slug : 'primary';
        }

        foreach (['show_logo', 'show_name', 'show_email'] as $boolKey) {
            if (array_key_exists($boolKey, $merged)) {
                $merged[$boolKey] = filter_var($merged[$boolKey], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function makeColumn(string $id, int $span, array $blocks): array
    {
        return [
            'id' => $id,
            'span' => $span,
            'blocks' => $blocks,
        ];
    }

    /**
     * @param  array<string, mixed>  $settingsOverride
     * @return array<string, mixed>
     */
    private function makeBlock(string $type, array $settingsOverride = []): array
    {
        return [
            'id' => 'blk_'.$type.'_'.Str::lower(Str::random(6)),
            'type' => $type,
            'enabled' => true,
            'settings' => array_merge(FooterBlockRegistry::defaultSettings($type), $settingsOverride),
        ];
    }
}
