<?php

namespace App\Services\Appearance;

use App\Models\AppMedia;
use App\Support\Media\LocalizedMediaMeta;
use App\Support\SiteLanguages;

/**
 * Resolve appearance builder media settings (id or legacy URL) for public presentation.
 *
 * Wrap a whole document in {@see withPrimed()} so every referenced media row loads in one query
 * instead of one `find()` per image.
 */
final class AppearanceMediaResolver
{
    /** @var array<int, AppMedia|null> Rows loaded by the active {@see withPrimed()} scope (null = missing). */
    private static array $primed = [];

    private static int $primeDepth = 0;

    /**
     * Run `$callback` with the given media ids preloaded; the cache is cleared when the outermost
     * scope ends, so long-running workers never serve stale rows.
     *
     * @template T
     *
     * @param  list<int>  $ids
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withPrimed(array $ids, callable $callback): mixed
    {
        self::$primeDepth++;
        try {
            self::prime($ids);

            return $callback();
        } finally {
            self::$primeDepth--;
            if (self::$primeDepth === 0) {
                self::$primed = [];
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function prime(array $ids): void
    {
        $missing = array_values(array_diff(array_unique(array_filter($ids, fn ($id) => $id > 0)), array_keys(self::$primed)));
        if ($missing === []) {
            return;
        }
        $rows = AppMedia::query()->with('translations')->whereIn('id', $missing)->get()->keyBy('id');
        foreach ($missing as $id) {
            self::$primed[$id] = $rows->get($id);
        }
    }

    /**
     * Media ids referenced by one block's settings: `media` fields, `media` sub-fields of repeaters,
     * hero floating icons, including every per-locale override in `translations`.
     *
     * @param  array<string, mixed>  $settings  Raw stored settings (with the `translations` bag)
     * @param  list<array<string, mixed>>  $fields
     * @return list<int>
     */
    public static function collectIds(array $settings, array $fields): array
    {
        $bags = [$settings];
        foreach (is_array($settings['translations'] ?? null) ? $settings['translations'] : [] as $bag) {
            if (is_array($bag)) {
                $bags[] = $bag;
            }
        }

        $ids = [];
        foreach ($bags as $bag) {
            foreach ($fields as $field) {
                $key = (string) ($field['key'] ?? '');
                $type = $field['type'] ?? '';
                if ($key === '' || ! array_key_exists($key, $bag)) {
                    continue;
                }
                if ($type === 'media') {
                    $ids[] = self::idOf($bag[$key]);
                } elseif ($type === 'repeater' && is_array($bag[$key])) {
                    $itemFields = is_array($field['item_fields'] ?? null) ? $field['item_fields'] : [];
                    foreach ($bag[$key] as $row) {
                        if (is_array($row)) {
                            array_push($ids, ...self::collectIds($row, $itemFields));
                        }
                    }
                } elseif ($type === 'floating_icons' && is_array($bag[$key])) {
                    foreach ($bag[$key] as $icon) {
                        $ids[] = is_array($icon) ? self::idOf($icon['media_id'] ?? null) : null;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id !== null)));
    }

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
            $id = self::idOf($value);
            if ($id === null) {
                return ['url' => '', 'alt' => null, 'media_id' => null];
            }
            $media = array_key_exists($id, self::$primed)
                ? self::$primed[$id]
                : AppMedia::query()->with('translations')->find($id);
            if (! $media) {
                return ['url' => '', 'alt' => null, 'media_id' => $id];
            }

            return [
                'url' => self::urlFor($media),
                'alt' => LocalizedMediaMeta::alt($media, $locale, $defaultLocale),
                'media_id' => $id,
            ];
        }

        if (is_string($value)) {
            return ['url' => trim($value), 'alt' => null, 'media_id' => null];
        }

        return ['url' => '', 'alt' => null, 'media_id' => null];
    }

    /** Absolute public URL of a media row ('' when it has none). */
    public static function urlFor(AppMedia $media): string
    {
        $url = $media->getUrl();
        if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url($url);
        }

        return (string) ($url ?: '');
    }

    private static function idOf(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit(trim($value))) {
            $id = (int) trim($value);

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
