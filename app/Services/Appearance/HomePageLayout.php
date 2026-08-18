<?php

namespace App\Services\Appearance;

use App\Models\ChromeLayout;
use App\Models\ThemeTemplate;
use Illuminate\Support\Facades\Schema;

/**
 * Page Builder document cloned from the live homepage (Theme body, else Appearance homepage).
 */
final class HomePageLayout
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function sections(): array
    {
        $fromTheme = self::rawThemeHomepageBodySections();
        if (self::bandTreeHasBlocks($fromTheme)) {
            return PageLayoutDocument::normalizeSectionsForPages($fromTheme);
        }

        $flat = app(HomepageBuilderService::class)->loadAdminDocument()['sections'] ?? [];

        return PageLayoutDocument::normalizeSectionsForPages(is_array($flat) ? $flat : []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rawThemeHomepageBodySections(): array
    {
        if (! Schema::hasTable('theme_templates') || ! Schema::hasTable('chrome_layouts')) {
            return [];
        }

        $ctx = ThemeTemplateConditions::contextFromResolver('homepage', null, 'homepage', null);
        $matched = app(ThemeTemplateService::class)->findBestMatch($ctx);
        if (! $matched instanceof ThemeTemplate || $matched->body_layout_id === null) {
            return [];
        }

        $body = ChromeLayout::query()
            ->whereKey((int) $matched->body_layout_id)
            ->where('kind', ChromeLayout::KIND_BODY)
            ->first();
        if (! $body instanceof ChromeLayout) {
            return [];
        }

        $doc = is_array($body->document) ? $body->document : [];
        $sections = $doc['sections'] ?? [];

        return is_array($sections) ? $sections : [];
    }

    /**
     * @param  list<mixed>  $sections
     */
    public static function bandTreeHasBlocks(array $sections): bool
    {
        foreach ($sections as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (isset($node['type']) && $node['type'] !== PageLayoutDocument::TYPE_LAYOUT && ! isset($node['rows'])) {
                return true;
            }
            foreach ($node['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ($row['columns'] ?? [] as $col) {
                    if (! is_array($col)) {
                        continue;
                    }
                    $blocks = $col['blocks'] ?? [];
                    if (is_array($blocks) && $blocks !== []) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
