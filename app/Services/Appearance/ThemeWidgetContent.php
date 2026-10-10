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
}
