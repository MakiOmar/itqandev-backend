<?php

namespace App\Services\Appearance;

/**
 * Fill Theme widgets (post title, content, …) with the current record's values on public render.
 *
 * Values the editor typed in the widget win; empty slots take the record value.
 */
final class ThemeWidgetContent
{
    /** Widget type => [settings key => dynamic tag id]. */
    private const SLOTS = [
        'post_title' => ['text' => DynamicTagRegistry::POST_TITLE],
        'post_excerpt' => ['text' => DynamicTagRegistry::POST_EXCERPT],
        'post_content' => ['html' => DynamicTagRegistry::POST_CONTENT],
        'post_featured_image' => ['image' => DynamicTagRegistry::POST_IMAGE],
        'post_info' => ['date' => DynamicTagRegistry::POST_DATE, 'terms' => DynamicTagRegistry::POST_TERMS],
        'archive_title' => ['text' => DynamicTagRegistry::ARCHIVE_TITLE],
    ];

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $tagContext
     * @return array<string, mixed>
     */
    public static function fill(string $type, array $settings, array $tagContext, ?string $locale = null): array
    {
        if ($type === 'list') {
            return self::fillList($settings, $tagContext);
        }
        if ($type === 'gallery') {
            return self::fillGallery($settings, $tagContext, $locale);
        }
        foreach (self::SLOTS[$type] ?? [] as $key => $tag) {
            $current = $settings[$key] ?? null;
            if (is_string($current) && trim($current) !== '') {
                continue;
            }
            $value = DynamicTagResolver::resolve($tag, $tagContext, $locale);
            if ($value !== '') {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * Dynamic List: one `{text, url}` item per related record of the allowlisted source; manual items otherwise.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $tagContext
     * @return array<string, mixed>
     */
    private static function fillList(array $settings, array $tagContext): array
    {
        if (! filter_var($settings['dynamic_items'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $settings;
        }

        $source = DynamicTagRegistry::parseListSource((string) ($settings['items_source'] ?? ''));
        $lists = is_array($tagContext[DynamicTagRegistry::LISTS_KEY] ?? null) ? $tagContext[DynamicTagRegistry::LISTS_KEY] : [];
        $rows = $source !== null && is_array($lists[$source['source']] ?? null) ? $lists[$source['source']] : [];
        $linked = filter_var($settings['link_items'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $items = [];
        foreach ($rows as $row) {
            $text = trim((string) ($row[$source['field']] ?? ''));
            if ($text !== '') {
                $items[] = ['text' => $text, 'url' => $linked ? (string) ($row['url'] ?? '') : ''];
            }
        }
        $settings['items'] = $items;
        $settings['items_resolved'] = true;

        return $settings;
    }

    /**
     * Dynamic Gallery: the record's gallery images (URL + localised alt) replace the manual images.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $tagContext
     * @return array<string, mixed>
     */
    private static function fillGallery(array $settings, array $tagContext, ?string $locale): array
    {
        if (! filter_var($settings['dynamic_images'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $settings;
        }

        $source = DynamicTagRegistry::parseGallerySource((string) ($settings['images_source'] ?? ''));
        $galleries = is_array($tagContext[DynamicTagRegistry::GALLERIES_KEY] ?? null) ? $tagContext[DynamicTagRegistry::GALLERIES_KEY] : [];
        $ids = $source !== null && is_array($galleries[$source] ?? null) ? array_values(array_filter($galleries[$source], 'is_int')) : [];

        $settings['images'] = AppearanceMediaResolver::withPrimed($ids, static function () use ($ids, $locale): array {
            $images = [];
            foreach ($ids as $id) {
                $resolved = AppearanceMediaResolver::resolve($id, $locale);
                if ($resolved['url'] !== '') {
                    $images[] = ['image' => $resolved['url'], 'alt' => (string) ($resolved['alt'] ?? '')];
                }
            }

            return $images;
        });
        $settings['images_resolved'] = true;

        return $settings;
    }
}
