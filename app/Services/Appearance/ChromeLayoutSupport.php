<?php

namespace App\Services\Appearance;

use App\Services\PublicMenuResolver;
use App\Support\SiteLanguages;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared helpers for header/footer page-layout documents in project settings.
 */
final class ChromeLayoutSupport
{
    /** @var list<string> */
    public const MENU_KIT_TYPES = ['header_menu', 'footer_menu'];

    /**
     * @param  array<string, mixed>  $input
     * @return array{sections: list<array<string, mixed>>}
     */
    public static function normalizeDocument(array $input): array
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages($input['sections'] ?? $input);

        return ['sections' => $sections];
    }

    /**
     * Present layout for public shell and resolve menu kit items.
     *
     * @return array{sections: list<array<string, mixed>>}
     */
    public static function presentPublic(array $document, ?string $locale = null): array
    {
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : SiteLanguages::defaultCode();

        $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
        $presented = PageLayoutDocument::presentPublicForPages($sections, $locale);

        return [
            'sections' => self::injectMenuItems($presented, $locale),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function injectMenuItems(array $sections, string $locale): array
    {
        foreach ($sections as &$band) {
            if (! is_array($band) || ($band['type'] ?? '') !== PageLayoutDocument::TYPE_LAYOUT) {
                continue;
            }
            $rows = is_array($band['rows'] ?? null) ? $band['rows'] : [];
            foreach ($rows as &$row) {
                if (! is_array($row)) {
                    continue;
                }
                $columns = is_array($row['columns'] ?? null) ? $row['columns'] : [];
                foreach ($columns as &$col) {
                    if (! is_array($col)) {
                        continue;
                    }
                    $blocks = is_array($col['blocks'] ?? null) ? $col['blocks'] : [];
                    foreach ($blocks as &$block) {
                        if (! is_array($block)) {
                            continue;
                        }
                        $type = (string) ($block['type'] ?? '');
                        if (! in_array($type, self::MENU_KIT_TYPES, true)) {
                            continue;
                        }
                        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
                        $slug = strtolower(trim((string) ($settings['menu_slug'] ?? 'primary')));
                        if ($slug === '') {
                            $slug = 'primary';
                        }
                        $settings['menu_slug'] = $slug;
                        $settings['items'] = [];
                        if (Schema::hasTable('menus')) {
                            try {
                                $settings['items'] = PublicMenuResolver::resolvePublishedTree($slug, $locale);
                            } catch (Throwable) {
                                $settings['items'] = [];
                            }
                        }
                        $block['settings'] = $settings;
                    }
                    unset($block);
                    $col['blocks'] = $blocks;
                }
                unset($col);
                $row['columns'] = $columns;
            }
            unset($row);
            $band['rows'] = $rows;
        }
        unset($band);

        return $sections;
    }

    /**
     * Convert legacy footer zones document into a page-layout document.
     *
     * @param  array<string, mixed>  $legacy
     * @return array{sections: list<array<string, mixed>>}|null
     */
    public static function migrateLegacyFooterZones(array $legacy): ?array
    {
        if (! isset($legacy['zones']) || ! is_array($legacy['zones'])) {
            return null;
        }
        if (isset($legacy['sections']) && is_array($legacy['sections']) && $legacy['sections'] !== []) {
            return null;
        }

        $typeMap = [
            'brand' => 'footer_brand',
            'contact' => 'footer_contact',
            'social' => 'footer_social',
            'menu' => 'footer_menu',
            'links' => 'footer_links',
            'rich_text' => 'footer_rich_text',
            'cta' => 'footer_cta',
        ];

        $sections = [];
        foreach (['top', 'main', 'bottom'] as $zoneKey) {
            $zone = $legacy['zones'][$zoneKey] ?? null;
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
                    if (! is_array($block) || ! ($block['enabled'] ?? true)) {
                        continue;
                    }
                    $oldType = strtolower(trim((string) ($block['type'] ?? '')));
                    $newType = $typeMap[$oldType] ?? null;
                    if ($newType === null || ! KitRegistry::has($newType)) {
                        continue;
                    }
                    $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
                    if ($newType === 'footer_rich_text' && $zoneKey === 'bottom') {
                        $newType = 'footer_copyright';
                        if (! isset($settings['text']) && isset($settings['body'])) {
                            $settings['text'] = strip_tags((string) $settings['body']);
                        }
                    }
                    $blocks[] = [
                        'id' => (string) ($block['id'] ?? ('blk_'.Str::lower(Str::random(8)))),
                        'kind' => 'kit',
                        'type' => $newType,
                        'enabled' => true,
                        'settings' => array_merge(KitRegistry::defaultSettings($newType), $settings),
                    ];
                }
                if ($blocks === []) {
                    continue;
                }
                $span = max(1, min(12, (int) ($col['span'] ?? 3)));
                $columns[] = [
                    'id' => (string) ($col['id'] ?? ('col_'.Str::lower(Str::random(8)))),
                    'span' => [
                        'mobile' => 12,
                        'tablet' => $span >= 6 ? 6 : 12,
                        'desktop' => $span,
                    ],
                    'blocks' => $blocks,
                ];
            }
            if ($columns === []) {
                continue;
            }
            $sections[] = [
                'id' => 'band_footer_'.$zoneKey,
                'type' => PageLayoutDocument::TYPE_LAYOUT,
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => [],
                'rows' => [
                    [
                        'id' => 'row_footer_'.$zoneKey,
                        'stack_below' => 'tablet',
                        'gap' => 6,
                        'columns' => $columns,
                    ],
                ],
            ];
        }

        if ($sections === []) {
            return null;
        }

        return self::normalizeDocument(['sections' => $sections]);
    }

    /**
     * @param  list<array{span: array{mobile: int, tablet: int, desktop: int}, blocks: list<array<string, mixed>>}>  $columns
     * @return array<string, mixed>
     */
    public static function makeBand(string $id, array $columns, string $layoutWidth = 'boxed', string $stackBelow = 'none'): array
    {
        $cols = [];
        foreach ($columns as $i => $col) {
            $cols[] = [
                'id' => $col['id'] ?? ($id.'_col_'.$i),
                'span' => $col['span'],
                'blocks' => $col['blocks'],
            ];
        }

        return [
            'id' => $id,
            'type' => PageLayoutDocument::TYPE_LAYOUT,
            'enabled' => true,
            'layout_width' => $layoutWidth,
            'settings' => [],
            'rows' => [
                [
                    'id' => $id.'_row',
                    'stack_below' => $stackBelow,
                    'gap' => 4,
                    'columns' => $cols,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $settingsOverride
     * @return array<string, mixed>
     */
    public static function makeKitBlock(string $type, array $settingsOverride = [], ?string $id = null): array
    {
        return [
            'id' => $id ?? ('kit_'.$type.'_'.Str::lower(Str::random(6))),
            'kind' => 'kit',
            'type' => $type,
            'enabled' => true,
            'settings' => array_merge(KitRegistry::defaultSettings($type), $settingsOverride),
        ];
    }
}
