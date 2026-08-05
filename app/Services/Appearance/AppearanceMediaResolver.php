<?php

namespace App\Services\Appearance;

use App\Models\AppMedia;
use App\Support\Media\LocalizedMediaMeta;
use App\Support\SiteLanguages;

/**
 * Resolve appearance builder media settings (id or legacy URL) for public presentation.
 */
final class AppearanceMediaResolver
{
    /**
     * @param  list<array{key?: string, type?: string}>  $fields
     * @param  array<string, mixed>  $settings  Locale-resolved flat settings (no translations bag)
     * @return array<string, mixed>
     */
    public static function expandMediaFields(array $settings, array $fields, ?string $locale = null): array
    {
        $defaultLocale = SiteLanguages::defaultCode();
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : $defaultLocale;

        foreach ($fields as $field) {
            if (! is_array($field) || ($field['type'] ?? '') !== 'media') {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $resolved = self::resolve($settings[$key] ?? null, $locale, $defaultLocale);
            $settings[$key] = $resolved['url'];
            $settings[$key.'_alt'] = $resolved['alt'];
            if ($resolved['media_id'] !== null) {
                $settings[$key.'_media_id'] = $resolved['media_id'];
            }
        }

        return $settings;
    }

    /**
     * Expand media fields nested inside repeater rows (e.g. gallery, team avatars).
     *
     * @param  list<array{key?: string, type?: string, item_fields?: list<array<string, mixed>>}>  $fields
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function expandRepeaterMediaFields(array $settings, array $fields, ?string $locale = null): array
    {
        foreach ($fields as $field) {
            if (! is_array($field) || ($field['type'] ?? '') !== 'repeater') {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            $itemFields = is_array($field['item_fields'] ?? null) ? $field['item_fields'] : [];
            if ($key === '' || $itemFields === [] || ! is_array($settings[$key] ?? null)) {
                continue;
            }
            $rows = [];
            foreach ($settings[$key] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = self::expandMediaFields($row, $itemFields, $locale);
            }
            $settings[$key] = $rows;
        }

        return $settings;
    }

    /**
     * @return array{url: string, alt: ?string, media_id: ?int}
     */
    public static function resolve(mixed $value, ?string $locale = null, ?string $defaultLocale = null): array
    {
        $defaultLocale = strtolower(trim((string) ($defaultLocale ?: SiteLanguages::defaultCode())));
        $locale = strtolower(trim((string) ($locale ?: $defaultLocale)));

        if ($value === null || $value === '') {
            return ['url' => '', 'alt' => null, 'media_id' => null];
        }

        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $id = (int) $value;
            if ($id <= 0) {
                return ['url' => '', 'alt' => null, 'media_id' => null];
            }

            /** @var AppMedia|null $media */
            $media = AppMedia::query()->with('translations')->find($id);
            if (! $media) {
                return ['url' => '', 'alt' => null, 'media_id' => $id];
            }

            $url = $media->getUrl();
            if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
                $url = url($url);
            }

            return [
                'url' => (string) ($url ?: ''),
                'alt' => LocalizedMediaMeta::alt($media, $locale, $defaultLocale),
                'media_id' => $id,
            ];
        }

        if (is_string($value)) {
            return ['url' => trim($value), 'alt' => null, 'media_id' => null];
        }

        return ['url' => '', 'alt' => null, 'media_id' => null];
    }
}
