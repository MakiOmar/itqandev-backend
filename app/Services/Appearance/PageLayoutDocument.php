<?php

namespace App\Services\Appearance;

use App\Support\SiteLanguages;
use Illuminate\Support\Str;

/**
 * CMS Pages layout documents: band → row → columns → leaf blocks.
 * Homepage Appearance stays flat via {@see ContentSectionDocument}.
 */
final class PageLayoutDocument
{
    public const TYPE_LAYOUT = 'layout';

    /** @var list<string> */
    private const STACK_BELOW = ['none', 'tablet', 'desktop'];

    /**
     * @param  array<string, mixed>|list<mixed>|null  $input
     * @return list<array<string, mixed>>
     */
    public static function normalizeSectionsForPages(mixed $input): array
    {
        $rawSections = [];
        if (is_array($input)) {
            if (array_is_list($input)) {
                $rawSections = $input;
            } elseif (isset($input['sections']) && is_array($input['sections'])) {
                $rawSections = $input['sections'];
            }
        }

        $blockCounts = [];
        $out = [];

        foreach ($rawSections as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = strtolower(trim((string) ($row['type'] ?? '')));

            if ($type === self::TYPE_LAYOUT || isset($row['rows'])) {
                $band = self::normalizeBand($row, $blockCounts);
                if ($band !== null) {
                    $out[] = $band;
                }

                continue;
            }

            // Legacy flat homepage-style section → wrap into layout tree.
            $flat = self::normalizeLegacyFlat($row, $blockCounts);
            if ($flat !== null) {
                $out[] = $flat;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function presentPublicForPages(array $sections, ?string $locale = null): array
    {
        $defaultLocale = SiteLanguages::defaultCode();
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : $defaultLocale;

        $out = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            if (! ($section['enabled'] ?? true)) {
                continue;
            }

            $type = strtolower(trim((string) ($section['type'] ?? '')));
            if ($type === self::TYPE_LAYOUT || isset($section['rows'])) {
                $presented = self::presentBand($section, $locale, $defaultLocale);
                if ($presented !== null) {
                    $out[] = $presented;
                }

                continue;
            }

            // Safety: present leftovers as single-block bands.
            $legacy = ContentSectionDocument::presentPublic([$section], $locale);
            if ($legacy === []) {
                continue;
            }
            $flat = $legacy[0];
            $out[] = self::wrapPresentedBlockAsBand($flat);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $blockCounts
     * @return array<string, mixed>|null
     */
    private static function normalizeBand(array $row, array &$blockCounts): ?array
    {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = 'band_'.Str::lower(Str::random(10));
        }

        $layout = strtolower(trim((string) ($row['layout_width'] ?? 'boxed')));
        if (! in_array($layout, ['boxed', 'full'], true)) {
            $layout = 'boxed';
        }

        $settings = is_array($row['settings'] ?? null) ? $row['settings'] : [];
        $rawRows = is_array($row['rows'] ?? null) ? $row['rows'] : [];
        $rows = [];
        foreach ($rawRows as $rawRow) {
            if (! is_array($rawRow)) {
                continue;
            }
            $normalizedRow = self::normalizeRow($rawRow, $blockCounts);
            if ($normalizedRow !== null) {
                $rows[] = $normalizedRow;
            }
        }

        if ($rows === []) {
            $rows[] = self::defaultEmptyRow();
        }

        return [
            'id' => $id,
            'type' => self::TYPE_LAYOUT,
            'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'layout_width' => $layout,
            'settings' => $settings,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $blockCounts
     * @return array<string, mixed>|null
     */
    private static function normalizeRow(array $row, array &$blockCounts): ?array
    {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = 'row_'.Str::lower(Str::random(10));
        }

        $stackBelow = strtolower(trim((string) ($row['stack_below'] ?? 'none')));
        if (! in_array($stackBelow, self::STACK_BELOW, true)) {
            $stackBelow = 'none';
        }

        $gap = isset($row['gap']) ? (int) $row['gap'] : 4;
        if ($gap < 0) {
            $gap = 0;
        }
        if ($gap > 16) {
            $gap = 16;
        }

        $rawCols = is_array($row['columns'] ?? null) ? $row['columns'] : [];
        $columns = [];
        foreach ($rawCols as $rawCol) {
            if (! is_array($rawCol)) {
                continue;
            }
            $col = self::normalizeColumn($rawCol, $blockCounts);
            if ($col !== null) {
                $columns[] = $col;
            }
        }

        if ($columns === []) {
            $columns[] = self::defaultEmptyColumn();
        }

        return [
            'id' => $id,
            'stack_below' => $stackBelow,
            'gap' => $gap,
            'columns' => $columns,
        ];
    }

    /**
     * @param  array<string, mixed>  $col
     * @param  array<string, int>  $blockCounts
     * @return array<string, mixed>|null
     */
    private static function normalizeColumn(array $col, array &$blockCounts): ?array
    {
        $id = trim((string) ($col['id'] ?? ''));
        if ($id === '') {
            $id = 'col_'.Str::lower(Str::random(10));
        }

        $span = self::normalizeSpans($col['span'] ?? null);
        $rawBlocks = is_array($col['blocks'] ?? null) ? $col['blocks'] : [];
        $blocks = [];
        foreach ($rawBlocks as $rawBlock) {
            if (! is_array($rawBlock)) {
                continue;
            }
            $block = self::normalizeBlock($rawBlock, $blockCounts);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return [
            'id' => $id,
            'span' => $span,
            'blocks' => $blocks,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, int>  $blockCounts
     * @return array<string, mixed>|null
     */
    private static function normalizeBlock(array $block, array &$blockCounts): ?array
    {
        $type = strtolower(trim((string) ($block['type'] ?? '')));
        if ($type === '' || $type === self::TYPE_LAYOUT) {
            return null;
        }

        $kind = PageLeafRegistry::inferKind($type, isset($block['kind']) ? (string) $block['kind'] : null);
        if ($kind === null) {
            return null;
        }

        $countKey = PageLeafRegistry::countKey($kind, $type);
        $blockCounts[$countKey] = ($blockCounts[$countKey] ?? 0) + 1;
        $max = PageLeafRegistry::maxInstances($kind, $type);
        if ($max !== null && $blockCounts[$countKey] > $max) {
            return null;
        }

        $id = trim((string) ($block['id'] ?? ''));
        if ($id === '') {
            $id = 'blk_'.Str::lower(Str::random(10));
        }

        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
        $settings = AppearanceLocalizedSettings::normalize(
            $settings,
            PageLeafRegistry::defaultSettings($kind, $type),
            PageLeafRegistry::translatableKeys($kind, $type),
        );

        return [
            'id' => $id,
            'kind' => $kind,
            'type' => $type,
            'enabled' => filter_var($block['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'settings' => $settings,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $blockCounts
     * @return array<string, mixed>|null
     */
    private static function normalizeLegacyFlat(array $row, array &$blockCounts): ?array
    {
        $type = strtolower(trim((string) ($row['type'] ?? '')));
        if (PageLeafRegistry::inferKind($type) === null) {
            return null;
        }

        $block = self::normalizeBlock([
            'id' => trim((string) ($row['id'] ?? '')),
            'type' => $type,
            'enabled' => $row['enabled'] ?? true,
            'settings' => is_array($row['settings'] ?? null) ? $row['settings'] : [],
        ], $blockCounts);

        if ($block === null) {
            return null;
        }

        $layout = strtolower(trim((string) ($row['layout_width'] ?? ($type === 'hero' ? 'full' : 'boxed'))));
        if (! in_array($layout, ['boxed', 'full'], true)) {
            $layout = 'boxed';
        }

        return [
            'id' => 'band_'.Str::lower(Str::random(8)),
            'type' => self::TYPE_LAYOUT,
            'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'layout_width' => $layout,
            'settings' => [],
            'rows' => [[
                'id' => 'row_'.Str::lower(Str::random(8)),
                'stack_below' => 'none',
                'gap' => 4,
                'columns' => [[
                    'id' => 'col_'.Str::lower(Str::random(8)),
                    'span' => self::fullSpans(),
                    'blocks' => [$block],
                ]],
            ]],
        ];
    }

    /**
     * @param  mixed  $span
     * @return array{mobile: int, tablet: int, desktop: int}
     */
    private static function normalizeSpans(mixed $span): array
    {
        if (is_numeric($span)) {
            $n = self::clampSpan((int) $span);

            return ['mobile' => $n, 'tablet' => $n, 'desktop' => $n];
        }

        if (! is_array($span)) {
            return self::fullSpans();
        }

        $desktop = self::clampSpan((int) ($span['desktop'] ?? $span['lg'] ?? 12));
        $tablet = self::clampSpan((int) ($span['tablet'] ?? $span['md'] ?? $desktop));
        $mobile = self::clampSpan((int) ($span['mobile'] ?? $span['sm'] ?? 12));

        return [
            'mobile' => $mobile,
            'tablet' => $tablet,
            'desktop' => $desktop,
        ];
    }

    private static function clampSpan(int $n): int
    {
        if ($n < 1) {
            return 1;
        }
        if ($n > 12) {
            return 12;
        }

        return $n;
    }

    /**
     * @return array{mobile: int, tablet: int, desktop: int}
     */
    private static function fullSpans(): array
    {
        return ['mobile' => 12, 'tablet' => 12, 'desktop' => 12];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultEmptyRow(): array
    {
        return [
            'id' => 'row_'.Str::lower(Str::random(8)),
            'stack_below' => 'none',
            'gap' => 4,
            'columns' => [self::defaultEmptyColumn()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultEmptyColumn(): array
    {
        return [
            'id' => 'col_'.Str::lower(Str::random(8)),
            'span' => self::fullSpans(),
            'blocks' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $band
     * @return array<string, mixed>|null
     */
    private static function presentBand(array $band, string $locale, string $defaultLocale): ?array
    {
        $rowsOut = [];
        $rawRows = is_array($band['rows'] ?? null) ? $band['rows'] : [];
        foreach ($rawRows as $rawRow) {
            if (! is_array($rawRow)) {
                continue;
            }
            $columnsOut = [];
            $rawCols = is_array($rawRow['columns'] ?? null) ? $rawRow['columns'] : [];
            foreach ($rawCols as $rawCol) {
                if (! is_array($rawCol)) {
                    continue;
                }
                $blocksOut = [];
                $rawBlocks = is_array($rawCol['blocks'] ?? null) ? $rawCol['blocks'] : [];
                foreach ($rawBlocks as $rawBlock) {
                    if (! is_array($rawBlock)) {
                        continue;
                    }
                    if (! ($rawBlock['enabled'] ?? true)) {
                        continue;
                    }
                    $presented = self::presentBlock($rawBlock, $locale, $defaultLocale);
                    if ($presented !== null) {
                        $blocksOut[] = $presented;
                    }
                }
                $columnsOut[] = [
                    'id' => (string) ($rawCol['id'] ?? ''),
                    'span' => self::normalizeSpans($rawCol['span'] ?? null),
                    'blocks' => $blocksOut,
                ];
            }

            $stackBelow = strtolower(trim((string) ($rawRow['stack_below'] ?? 'none')));
            if (! in_array($stackBelow, self::STACK_BELOW, true)) {
                $stackBelow = 'none';
            }

            $rowsOut[] = [
                'id' => (string) ($rawRow['id'] ?? ''),
                'stack_below' => $stackBelow,
                'gap' => (int) ($rawRow['gap'] ?? 4),
                'columns' => $columnsOut,
            ];
        }

        $layout = strtolower(trim((string) ($band['layout_width'] ?? 'boxed')));
        if (! in_array($layout, ['boxed', 'full'], true)) {
            $layout = 'boxed';
        }

        return [
            'id' => (string) ($band['id'] ?? ''),
            'type' => self::TYPE_LAYOUT,
            'layout_width' => $layout,
            'settings' => is_array($band['settings'] ?? null) ? $band['settings'] : [],
            'rows' => $rowsOut,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private static function presentBlock(array $block, string $locale, string $defaultLocale): ?array
    {
        $type = strtolower(trim((string) ($block['type'] ?? '')));
        $kind = PageLeafRegistry::inferKind($type, isset($block['kind']) ? (string) $block['kind'] : null);
        if ($kind === null) {
            return null;
        }

        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
        $settings = AppearanceLocalizedSettings::resolveForLocale(
            $settings,
            $locale,
            $defaultLocale,
            PageLeafRegistry::translatableKeys($kind, $type),
        );
        $entry = $kind === PageLeafRegistry::KIND_WIDGET
            ? (WidgetRegistry::all()[$type] ?? null)
            : (KitRegistry::all()[$type] ?? null);
        $fields = is_array($entry['settings_fields'] ?? null) ? $entry['settings_fields'] : [];
        $settings = AppearanceMediaResolver::expandMediaFields($settings, $fields, $locale);
        $settings = AppearanceMediaResolver::expandRepeaterMediaFields($settings, $fields, $locale);
        if ($type === 'hero') {
            $enabled = filter_var($settings['floating_icons_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $settings['floating_icons_enabled'] = $enabled;
            $settings['floating_icons'] = $enabled
                ? HeroFloatingIcons::presentPublic(
                    is_array($settings['floating_icons'] ?? null) ? $settings['floating_icons'] : [],
                    $locale,
                )
                : [];
        }

        return [
            'id' => (string) ($block['id'] ?? ''),
            'kind' => $kind,
            'type' => $type,
            'settings' => $settings,
        ];
    }

    /**
     * @param  array{id: string, type: string, layout_width: string, settings: array<string, mixed>}  $flat
     * @return array<string, mixed>
     */
    private static function wrapPresentedBlockAsBand(array $flat): array
    {
        return [
            'id' => 'band_'.$flat['id'],
            'type' => self::TYPE_LAYOUT,
            'layout_width' => $flat['layout_width'],
            'settings' => [],
            'rows' => [[
                'id' => 'row_'.$flat['id'],
                'stack_below' => 'none',
                'gap' => 4,
                'columns' => [[
                    'id' => 'col_'.$flat['id'],
                    'span' => self::fullSpans(),
                    'blocks' => [[
                        'id' => $flat['id'],
                        'type' => $flat['type'],
                        'settings' => $flat['settings'],
                    ]],
                ]],
            ]],
        ];
    }
}
