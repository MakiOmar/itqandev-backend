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

    public const TYPE_INNER_BAND = 'inner_band';

    public const DOCUMENT_VERSION = 2;

    /** @var list<string> */
    private const STACK_BELOW = ['none', 'tablet', 'desktop'];

    /** @var list<string> */
    private const FLEX_DIRECTION = ['row', 'column'];

    /** @var list<string> */
    private const FLEX_JUSTIFY = ['start', 'center', 'end', 'between'];

    /** @var list<string> */
    private const FLEX_ALIGN = ['start', 'center', 'end', 'stretch'];

    /** @var list<string> */
    private const COLUMN_FLEX_JUSTIFY = ['start', 'center', 'end', 'between', 'around', 'evenly'];

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

        $out = [];

        foreach ($rawSections as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = strtolower(trim((string) ($row['type'] ?? '')));

            if ($type === self::TYPE_LAYOUT || isset($row['rows'])) {
                $band = self::normalizeBand($row);
                if ($band !== null) {
                    $out[] = $band;
                }

                continue;
            }

            // Legacy flat homepage-style section → wrap into layout tree.
            $flat = self::normalizeLegacyFlat($row);
            if ($flat !== null) {
                $out[] = $flat;
            }
        }

        return $out;
    }

    /**
     * Public wrapper so global widgets can reuse leaf normalization.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    public static function normalizeLeafForGlobal(array $block): ?array
    {
        return self::normalizeBlock($block, false);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $tagContext
     * @return list<array<string, mixed>>
     */
    public static function presentPublicForPages(array $sections, ?string $locale = null, ?array $tagContext = null): array
    {
        $defaultLocale = SiteLanguages::defaultCode();
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : $defaultLocale;

        $sections = GlobalWidgetService::resolveSections($sections);

        return AppearanceMediaResolver::withPrimed(
            self::collectMediaIds($sections),
            fn () => self::presentSections($sections, $locale, $defaultLocale, $tagContext),
        );
    }

    /**
     * Every media id referenced by leaf settings anywhere in the document (bands, rows, columns,
     * inner bands), so presentation can load them in one query.
     *
     * @param  array<int|string, mixed>  $node
     * @return list<int>
     */
    public static function collectMediaIds(array $node): array
    {
        $ids = [];
        if (isset($node['type']) && is_string($node['type']) && is_array($node['settings'] ?? null)) {
            $type = strtolower(trim($node['type']));
            $kind = PageLeafRegistry::inferKind($type, isset($node['kind']) ? (string) $node['kind'] : null);
            $entry = match ($kind) {
                PageLeafRegistry::KIND_WIDGET => WidgetRegistry::all()[$type] ?? null,
                PageLeafRegistry::KIND_KIT => KitRegistry::all()[$type] ?? null,
                default => null,
            };
            $fields = is_array($entry['settings_fields'] ?? null) ? $entry['settings_fields'] : [];
            $ids = AppearanceMediaResolver::collectIds($node['settings'], $fields);
        }
        foreach (['rows', 'columns', 'blocks'] as $childKey) {
            foreach (is_array($node[$childKey] ?? null) ? $node[$childKey] : [] as $child) {
                if (is_array($child)) {
                    array_push($ids, ...self::collectMediaIds($child));
                }
            }
        }
        if (array_is_list($node)) {
            foreach ($node as $child) {
                if (is_array($child)) {
                    array_push($ids, ...self::collectMediaIds($child));
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>|null  $tagContext
     * @return list<array<string, mixed>>
     */
    private static function presentSections(array $sections, string $locale, string $defaultLocale, ?array $tagContext): array
    {
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
                $presented = self::presentBand($section, $locale, $defaultLocale, $tagContext);
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
     * @return array<string, mixed>|null
     */
    private static function normalizeBand(array $row): ?array
    {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = 'band_'.Str::lower(Str::random(10));
        }

        $layout = strtolower(trim((string) ($row['layout_width'] ?? 'boxed')));
        if (! in_array($layout, ['boxed', 'full'], true)) {
            $layout = 'boxed';
        }

        $settings = BuilderBackgroundDocument::normalizeLayoutSettings($row['settings'] ?? null);
        $rawRows = is_array($row['rows'] ?? null) ? $row['rows'] : [];
        $rows = [];
        foreach ($rawRows as $rawRow) {
            if (! is_array($rawRow)) {
                continue;
            }
            $normalizedRow = self::normalizeRow($rawRow, true);
            if ($normalizedRow !== null) {
                $rows[] = $normalizedRow;
            }
        }

        if ($rows === []) {
            $rows[] = self::defaultEmptyRow();
        }

        $band = [
            'id' => $id,
            'type' => self::TYPE_LAYOUT,
            'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'layout_width' => $layout,
            'settings' => $settings,
            'rows' => $rows,
        ];

        $band = LayoutHideOn::appendTo($band, $row['hide_on'] ?? null);

        return BuilderStyleDocument::appendTo($band, $row['styles'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private static function normalizeRow(array $row, bool $allowInnerBand): ?array
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

        $direction = strtolower(trim((string) ($row['direction'] ?? 'row')));
        if (! in_array($direction, self::FLEX_DIRECTION, true)) {
            $direction = 'row';
        }
        $justify = strtolower(trim((string) ($row['justify'] ?? 'start')));
        if (! in_array($justify, self::FLEX_JUSTIFY, true)) {
            $justify = 'start';
        }
        $align = strtolower(trim((string) ($row['align'] ?? 'stretch')));
        if (! in_array($align, self::FLEX_ALIGN, true)) {
            $align = 'stretch';
        }
        $wrap = filter_var($row['wrap'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $rawCols = is_array($row['columns'] ?? null) ? $row['columns'] : [];
        $columns = [];
        foreach ($rawCols as $rawCol) {
            if (! is_array($rawCol)) {
                continue;
            }
            $col = self::normalizeColumn($rawCol, $allowInnerBand);
            if ($col !== null) {
                $columns[] = $col;
            }
        }

        if ($columns === []) {
            $columns[] = self::defaultEmptyColumn();
        }

        $normalized = [
            'id' => $id,
            'stack_below' => $stackBelow,
            'gap' => $gap,
            'direction' => $direction,
            'justify' => $justify,
            'align' => $align,
            'wrap' => $wrap,
            'settings' => BuilderBackgroundDocument::normalizeLayoutSettings($row['settings'] ?? null),
            'columns' => $columns,
        ];

        $normalized = LayoutHideOn::appendTo($normalized, $row['hide_on'] ?? null);

        return BuilderStyleDocument::appendTo($normalized, $row['styles'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $col
     * @return array<string, mixed>|null
     */
    private static function normalizeColumn(array $col, bool $allowInnerBand): ?array
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
            $block = self::normalizeBlock($rawBlock, $allowInnerBand);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        $normalized = [
            'id' => $id,
            'span' => $span,
            'settings' => BuilderBackgroundDocument::normalizeLayoutSettings($col['settings'] ?? null),
            'blocks' => $blocks,
        ];

        $normalized = self::appendColumnFlex($normalized, $col['flex'] ?? null);
        $normalized = LayoutHideOn::appendTo($normalized, $col['hide_on'] ?? null);

        return BuilderStyleDocument::appendTo($normalized, $col['styles'] ?? null);
    }

    /**
     * Columns without a valid `flex` object keep the legacy vertical stack, so the key is omitted.
     *
     * @param  array<string, mixed>  $column
     * @return array<string, mixed>
     */
    private static function appendColumnFlex(array $column, mixed $flex): array
    {
        if (! is_array($flex)) {
            return $column;
        }

        $direction = strtolower(trim((string) ($flex['direction'] ?? 'column')));
        $justify = strtolower(trim((string) ($flex['justify'] ?? 'start')));
        $align = strtolower(trim((string) ($flex['align'] ?? 'stretch')));
        $gap = (int) ($flex['gap'] ?? 6);

        $column['flex'] = [
            'direction' => in_array($direction, self::FLEX_DIRECTION, true) ? $direction : 'column',
            'justify' => in_array($justify, self::COLUMN_FLEX_JUSTIFY, true) ? $justify : 'start',
            'align' => in_array($align, self::FLEX_ALIGN, true) ? $align : 'stretch',
            'wrap' => filter_var($flex['wrap'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'gap' => max(0, min(16, $gap)),
        ];

        return $column;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private static function normalizeBlock(array $block, bool $allowInnerBand = true): ?array
    {
        $kindHint = strtolower(trim((string) ($block['kind'] ?? '')));
        if ($kindHint === PageLeafRegistry::KIND_GLOBAL) {
            $gid = (int) ($block['global_id'] ?? 0);
            if ($gid < 1) {
                return null;
            }
            $id = trim((string) ($block['id'] ?? ''));
            if ($id === '') {
                $id = 'glb_'.Str::lower(Str::random(10));
            }
            $normalized = [
                'id' => $id,
                'kind' => PageLeafRegistry::KIND_GLOBAL,
                'type' => 'global',
                'global_id' => $gid,
                'enabled' => filter_var($block['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'settings' => [],
            ];
            $normalized = LayoutHideOn::appendTo($normalized, $block['hide_on'] ?? null);

            return BuilderStyleDocument::appendTo($normalized, $block['styles'] ?? null);
        }

        $type = strtolower(trim((string) ($block['type'] ?? '')));
        if ($type === self::TYPE_INNER_BAND) {
            if (! $allowInnerBand) {
                return null;
            }

            return self::normalizeInnerBand($block);
        }
        if ($type === '' || $type === self::TYPE_LAYOUT) {
            return null;
        }

        $kind = PageLeafRegistry::inferKind($type, isset($block['kind']) ? (string) $block['kind'] : null);
        if ($kind === null) {
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
            PageLeafRegistry::sharedRepeaterKeys($kind, $type),
        );
        $entry = $kind === PageLeafRegistry::KIND_WIDGET
            ? (WidgetRegistry::all()[$type] ?? null)
            : (KitRegistry::all()[$type] ?? null);
        $fields = is_array($entry['settings_fields'] ?? null) ? $entry['settings_fields'] : [];
        $settings = ControlNormalizer::normalizeSettings($settings, $fields);
        // Layout/widget Style → Background lives on settings.background (not kit fields).
        $incomingBg = is_array($block['settings'] ?? null)
            ? ($block['settings']['background'] ?? null)
            : null;
        if ($incomingBg !== null) {
            $bg = BuilderBackgroundDocument::normalize($incomingBg);
            if ($bg !== null) {
                $settings['background'] = $bg;
            }
        }

        $normalized = [
            'id' => $id,
            'kind' => $kind,
            'type' => $type,
            'enabled' => filter_var($block['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'settings' => $settings,
        ];
        $normalized = LayoutHideOn::appendTo($normalized, $block['hide_on'] ?? null);

        return BuilderStyleDocument::appendTo($normalized, $block['styles'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private static function normalizeInnerBand(array $block): ?array
    {
        $id = trim((string) ($block['id'] ?? ''));
        if ($id === '') {
            $id = 'inner_'.Str::lower(Str::random(10));
        }
        $rawRows = is_array($block['rows'] ?? null) ? $block['rows'] : [];
        $rows = [];
        foreach ($rawRows as $rawRow) {
            if (! is_array($rawRow)) {
                continue;
            }
            $row = self::normalizeRow($rawRow, false);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        if ($rows === []) {
            $rows[] = self::defaultEmptyRow();
        }
        $normalized = [
            'id' => $id,
            'kind' => PageLeafRegistry::KIND_INNER,
            'type' => self::TYPE_INNER_BAND,
            'enabled' => filter_var($block['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'settings' => BuilderBackgroundDocument::normalizeLayoutSettings($block['settings'] ?? null),
            'rows' => $rows,
        ];
        $normalized = LayoutHideOn::appendTo($normalized, $block['hide_on'] ?? null);

        return BuilderStyleDocument::appendTo($normalized, $block['styles'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private static function normalizeLegacyFlat(array $row): ?array
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
            'hide_on' => $row['hide_on'] ?? null,
            'styles' => $row['styles'] ?? null,
        ]);

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
     * @param  array<string, mixed>|null  $tagContext
     * @return array<string, mixed>|null
     */
    private static function presentBand(array $band, string $locale, string $defaultLocale, ?array $tagContext = null): ?array
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
                    $presented = self::presentBlock($rawBlock, $locale, $defaultLocale, $tagContext);
                    if ($presented !== null) {
                        $blocksOut[] = $presented;
                    }
                }
                $column = [
                    'id' => (string) ($rawCol['id'] ?? ''),
                    'span' => self::normalizeSpans($rawCol['span'] ?? null),
                    'settings' => BuilderBackgroundDocument::normalizeLayoutSettings($rawCol['settings'] ?? null),
                    'blocks' => $blocksOut,
                ];
                $column = self::appendColumnFlex($column, $rawCol['flex'] ?? null);
                $column = LayoutHideOn::appendTo($column, $rawCol['hide_on'] ?? null);
                $columnsOut[] = BuilderStyleDocument::appendTo($column, $rawCol['styles'] ?? null);
            }

            $stackBelow = strtolower(trim((string) ($rawRow['stack_below'] ?? 'none')));
            if (! in_array($stackBelow, self::STACK_BELOW, true)) {
                $stackBelow = 'none';
            }
            $direction = strtolower(trim((string) ($rawRow['direction'] ?? 'row')));
            if (! in_array($direction, self::FLEX_DIRECTION, true)) {
                $direction = 'row';
            }
            $justify = strtolower(trim((string) ($rawRow['justify'] ?? 'start')));
            if (! in_array($justify, self::FLEX_JUSTIFY, true)) {
                $justify = 'start';
            }
            $align = strtolower(trim((string) ($rawRow['align'] ?? 'stretch')));
            if (! in_array($align, self::FLEX_ALIGN, true)) {
                $align = 'stretch';
            }

            $rowOut = [
                'id' => (string) ($rawRow['id'] ?? ''),
                'stack_below' => $stackBelow,
                'gap' => (int) ($rawRow['gap'] ?? 4),
                'direction' => $direction,
                'justify' => $justify,
                'align' => $align,
                'wrap' => filter_var($rawRow['wrap'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'settings' => BuilderBackgroundDocument::normalizeLayoutSettings($rawRow['settings'] ?? null),
                'columns' => $columnsOut,
            ];
            $rowOut = LayoutHideOn::appendTo($rowOut, $rawRow['hide_on'] ?? null);
            $rowsOut[] = BuilderStyleDocument::appendTo($rowOut, $rawRow['styles'] ?? null);
        }

        $layout = strtolower(trim((string) ($band['layout_width'] ?? 'boxed')));
        if (! in_array($layout, ['boxed', 'full'], true)) {
            $layout = 'boxed';
        }

        $presented = [
            'id' => (string) ($band['id'] ?? ''),
            'type' => self::TYPE_LAYOUT,
            'layout_width' => $layout,
            'settings' => BuilderBackgroundDocument::normalizeLayoutSettings($band['settings'] ?? null),
            'rows' => $rowsOut,
        ];
        $presented = LayoutHideOn::appendTo($presented, $band['hide_on'] ?? null);

        return BuilderStyleDocument::appendTo($presented, $band['styles'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>|null  $tagContext
     * @return array<string, mixed>|null
     */
    private static function presentBlock(array $block, string $locale, string $defaultLocale, ?array $tagContext = null): ?array
    {
        $type = strtolower(trim((string) ($block['type'] ?? '')));
        if ($type === self::TYPE_INNER_BAND) {
            $inner = self::presentBand([
                'id' => (string) ($block['id'] ?? ''),
                'type' => self::TYPE_LAYOUT,
                'layout_width' => 'boxed',
                'settings' => $block['settings'] ?? [],
                'rows' => is_array($block['rows'] ?? null) ? $block['rows'] : [],
                'hide_on' => $block['hide_on'] ?? null,
                'styles' => $block['styles'] ?? null,
            ], $locale, $defaultLocale, $tagContext);
            if ($inner === null) {
                return null;
            }
            $inner['type'] = self::TYPE_INNER_BAND;
            $inner['kind'] = PageLeafRegistry::KIND_INNER;

            return $inner;
        }

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
            PageLeafRegistry::sharedRepeaterKeys($kind, $type),
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
        if ($tagContext !== null) {
            $settings = DynamicTagResolver::apply($settings, $tagContext, $locale);
        }
        if ($type === 'loop_grid') {
            $settings['items'] = LoopQueryService::items($settings, $locale);
        }

        $presented = [
            'id' => (string) ($block['id'] ?? ''),
            'kind' => $kind,
            'type' => $type,
            'settings' => $settings,
        ];
        $presented = LayoutHideOn::appendTo($presented, $block['hide_on'] ?? null);

        return BuilderStyleDocument::appendTo($presented, $block['styles'] ?? null);
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
