<?php

namespace App\Services\Appearance;

/**
 * Primary flat settings + optional settings.translations.{locale} for text fields.
 */
final class AppearanceLocalizedSettings
{
    /**
     * @param  list<array{key: string, type: string, translatable?: bool}>  $fields
     * @return list<string>
     */
    public static function translatableKeysFromFields(array $fields): array
    {
        $keys = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            if (! self::isFieldTranslatable($field)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array{key?: string, type?: string, translatable?: bool}  $field
     */
    public static function isFieldTranslatable(array $field): bool
    {
        if (array_key_exists('translatable', $field)) {
            return (bool) $field['translatable'];
        }

        return in_array((string) ($field['type'] ?? ''), ['text', 'textarea'], true);
    }

    /**
     * Keep primary scalars; normalize translations bags to known translatable keys only.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $defaults
     * @param  list<string>  $translatableKeys
     * @return array<string, mixed>
     */
    public static function normalize(array $settings, array $defaults, array $translatableKeys): array
    {
        $translationsIn = is_array($settings['translations'] ?? null) ? $settings['translations'] : [];
        unset($settings['translations']);

        $merged = array_merge($defaults, $settings);

        foreach ($merged as $key => $value) {
            if ($key === 'translations') {
                continue;
            }
            if (is_string($value)) {
                $merged[$key] = trim($value);
            }
            if ($key === 'limit') {
                $merged[$key] = max(1, min(24, (int) $value));
            }
            if ($key === 'floating_icons') {
                $merged[$key] = HeroFloatingIcons::normalize($value);
            }
            if ($key === 'floating_icons_enabled' || $key === 'full_viewport' || $key === 'watermark_enabled') {
                $merged[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            if ($key === 'nav_top_space') {
                $merged[$key] = max(0, min(200, (int) $value));
            }
        }

        $normalizedTranslations = [];
        $keySet = array_fill_keys($translatableKeys, true);
        foreach ($translationsIn as $locale => $bag) {
            if (! is_array($bag)) {
                continue;
            }
            $code = strtolower(trim((string) $locale));
            if ($code === '') {
                continue;
            }
            $out = [];
            foreach ($bag as $key => $value) {
                $key = (string) $key;
                if (! isset($keySet[$key])) {
                    continue;
                }
                if (is_string($value)) {
                    $value = trim($value);
                }
                $out[$key] = $value;
            }
            if ($out !== []) {
                $normalizedTranslations[$code] = $out;
            }
        }

        if ($normalizedTranslations !== []) {
            $merged['translations'] = $normalizedTranslations;
        }

        return $merged;
    }

    /**
     * Flatten locale overlay for public render (strips translations key).
     * Empty secondary strings fall back to primary.
     *
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $translatableKeys
     * @return array<string, mixed>
     */
    public static function resolveForLocale(
        array $settings,
        ?string $locale,
        string $defaultLocale,
        array $translatableKeys,
    ): array {
        $locale = strtolower(trim((string) $locale));
        $defaultLocale = strtolower(trim($defaultLocale));
        $translations = is_array($settings['translations'] ?? null) ? $settings['translations'] : [];
        unset($settings['translations']);

        if ($locale === '' || $locale === $defaultLocale) {
            return $settings;
        }

        $bag = is_array($translations[$locale] ?? null) ? $translations[$locale] : [];
        foreach ($translatableKeys as $key) {
            if (! array_key_exists($key, $bag)) {
                continue;
            }
            $value = $bag[$key];
            if (is_string($value) && trim($value) === '') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            $settings[$key] = $value;
        }

        return $settings;
    }
}
