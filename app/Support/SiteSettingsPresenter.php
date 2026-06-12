<?php

namespace App\Support;

/**
 * Localizes operator-managed settings text for the public marketing site.
 */
final class SiteSettingsPresenter
{
    /** Top-level scalar keys stored in settings_translations per locale. */
    public const SCALAR_KEYS = [
        'site_name',
        'site_description',
        'site_address',
    ];

    /**
     * @return list<string>
     */
    public static function translatableFieldPaths(): array
    {
        return array_merge(
            self::SCALAR_KEYS,
            ['marketing_site_content'],
        );
    }

    /**
     * @param  array<string, mixed>  $settings  Normalized settings (includes primary locale fields).
     * @return array<string, mixed>
     */
    public static function apply(array $settings, ?string $locale): array
    {
        $locale = self::normalizeLocaleCode($locale);
        $default = SiteLanguages::defaultCode();

        if ($locale === null || $locale === '' || $locale === $default) {
            return self::stripInternalKeys($settings);
        }

        $translations = is_array($settings['settings_translations'] ?? null)
            ? $settings['settings_translations']
            : [];
        $overlay = is_array($translations[$locale] ?? null) ? $translations[$locale] : [];

        if ($overlay === []) {
            return self::stripInternalKeys($settings);
        }

        $localized = $settings;

        foreach (self::SCALAR_KEYS as $key) {
            if (isset($overlay[$key]) && is_string($overlay[$key]) && trim($overlay[$key]) !== '') {
                $localized[$key] = $overlay[$key];
                if ($key === 'site_name') {
                    $localized['name'] = $overlay[$key];
                }
                if ($key === 'site_description') {
                    $localized['description'] = $overlay[$key];
                }
            }
        }

        if (isset($overlay['marketing_site_content']) && is_array($overlay['marketing_site_content'])) {
            $primary = is_array($localized['marketing_site_content'] ?? null)
                ? $localized['marketing_site_content']
                : [];
            $localized['marketing_site_content'] = self::deepMerge($primary, $overlay['marketing_site_content']);
        }

        return self::stripInternalKeys($localized);
    }

    /**
     * Merge incoming translation rows onto existing (per settings tab partial saves).
     *
     * @param  array<string, array<string, mixed>>  $existing
     * @param  array<string, array<string, mixed>>  $incoming
     * @return array<string, array<string, mixed>>
     */
    public static function mergeTranslationsStorage(array $existing, array $incoming): array
    {
        foreach ($incoming as $locale => $row) {
            if (! is_string($locale) && ! is_int($locale)) {
                continue;
            }
            $code = strtolower(trim((string) $locale));
            if ($code === '' || ! is_array($row)) {
                continue;
            }
            $prev = is_array($existing[$code] ?? null) ? $existing[$code] : [];
            $merged = $prev;

            foreach (self::SCALAR_KEYS as $key) {
                if (array_key_exists($key, $row)) {
                    $merged[$key] = $row[$key];
                }
            }

            if (isset($row['marketing_site_content']) && is_array($row['marketing_site_content'])) {
                $baseMarketing = is_array($merged['marketing_site_content'] ?? null)
                    ? $merged['marketing_site_content']
                    : [];
                $merged['marketing_site_content'] = self::deepMerge(
                    $baseMarketing,
                    $row['marketing_site_content']
                );
            }

            $existing[$code] = $merged;
        }

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $input  Raw settings_translations from request.
     * @return array<string, array<string, mixed>>
     */
    public static function normalizeTranslationsPayload(array $input): array
    {
        $allowedCodes = array_map(
            fn ($row) => strtolower((string) ($row['code'] ?? '')),
            SiteLanguages::all()
        );
        $default = SiteLanguages::defaultCode();
        $out = [];

        foreach ($input as $code => $row) {
            if (! is_string($code) && ! is_int($code)) {
                continue;
            }
            $locale = strtolower(trim((string) $code));
            if ($locale === '' || $locale === $default || ! in_array($locale, $allowedCodes, true)) {
                continue;
            }
            if (! is_array($row)) {
                continue;
            }
            $normalized = self::normalizeTranslationRow($row);
            if ($normalized !== []) {
                $out[$locale] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function normalizeTranslationRow(array $row): array
    {
        $out = [];

        foreach (self::SCALAR_KEYS as $key) {
            if (array_key_exists($key, $row) && is_string($row[$key])) {
                $out[$key] = $row[$key];
            }
        }

        if (isset($row['marketing_site_content']) && is_array($row['marketing_site_content'])) {
            $out['marketing_site_content'] = $row['marketing_site_content'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function stripInternalKeys(array $settings): array
    {
        unset($settings['settings_translations']);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public static function marketingContentPayload(array $settings): array
    {
        $marketing = is_array($settings['marketing_site_content'] ?? null)
            ? $settings['marketing_site_content']
            : [];

        $contact = is_array($marketing['contact'] ?? null)
            ? $marketing['contact']
            : [
                'email' => $settings['site_email'] ?? $settings['supportEmail'] ?? null,
                'phone' => $settings['site_phone'] ?? $settings['supportPhone'] ?? null,
                'address' => $settings['site_address'] ?? null,
                'socials' => [],
            ];

        if (! isset($contact['address']) || $contact['address'] === null || $contact['address'] === '') {
            $contact['address'] = $settings['site_address'] ?? null;
        }

        return [
            'pricingTiers' => $marketing['pricingTiers'] ?? $marketing['pricing_tiers'] ?? [],
            'faq' => $marketing['faq'] ?? [],
            'contact' => $contact,
            'about' => $marketing['about'] ?? [],
            'techStack' => $marketing['techStack'] ?? $marketing['tech_stack'] ?? [],
        ];
    }

    private static function normalizeLocaleCode(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }
        $code = strtolower(trim($locale));
        if ($code === '') {
            return null;
        }
        foreach (SiteLanguages::all() as $row) {
            if ($row['code'] === $code) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    private static function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                if (self::isListArray($value) && self::isListArray($base[$key])) {
                    $base[$key] = self::mergeListArrays($base[$key], $value);
                } else {
                    $base[$key] = self::deepMerge($base[$key], $value);
                }
            } elseif (is_string($value)) {
                if (trim($value) !== '') {
                    $base[$key] = $value;
                }
            } elseif ($value !== '') {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param  array<int, mixed>  $base
     * @param  array<int, mixed>  $overlay
     * @return array<int, mixed>
     */
    private static function mergeListArrays(array $base, array $overlay): array
    {
        foreach ($overlay as $index => $item) {
            if (! is_int($index) && ! is_string($index)) {
                continue;
            }
            $i = (int) $index;
            if (isset($base[$i]) && is_array($base[$i]) && is_array($item)) {
                $base[$i] = self::deepMerge($base[$i], $item);
            } elseif ($item !== null && $item !== '') {
                $base[$i] = $item;
            }
        }

        return array_values($base);
    }

    /**
     * @param  array<mixed>  $arr
     */
    private static function isListArray(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
