<?php

namespace App\Support;

/**
 * Reads site language configuration from project settings (database).
 */
final class SiteLanguages
{

    /**
     * @return array<int, array{code: string, label: string, native_label: string, rtl: bool}>
     */
    public static function all(): array
    {
        $decoded = self::loadRaw();
        $langs = $decoded['site_languages'] ?? null;
        if (! is_array($langs) || $langs === []) {
            return self::defaults();
        }

        return self::normalizeList($langs);
    }

    public static function defaultCode(): string
    {
        $decoded = self::loadRaw();
        $code = $decoded['default_locale'] ?? 'en';
        $code = is_string($code) ? strtolower(trim($code)) : 'en';

        foreach (self::all() as $row) {
            if ($row['code'] === $code) {
                return $code;
            }
        }

        return self::all()[0]['code'] ?? 'en';
    }

    /**
     * Locale codes enabled for the site except the default (for translation rows).
     *
     * @return list<string>
     */
    public static function secondaryLocaleCodes(): array
    {
        $def = self::defaultCode();
        $codes = [];
        foreach (self::all() as $row) {
            if ($row['code'] !== $def) {
                $codes[] = $row['code'];
            }
        }

        return $codes;
    }

    /**
     * Normalize a stored content primary locale; invalid or empty becomes null (use site default).
     */
    public static function normalizeContentLocale(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $c = strtolower(trim($raw));
        if ($c === '') {
            return null;
        }
        foreach (self::all() as $row) {
            if ($row['code'] === $c) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Effective primary locale for a project/post row (main column language).
     */
    public static function primaryLocaleForContent(?string $storedContentLocale): string
    {
        $n = self::normalizeContentLocale($storedContentLocale);

        return $n ?? self::defaultCode();
    }

    /**
     * Locales that may have translation rows for this record (all enabled except its primary).
     *
     * @return list<string>
     */
    public static function secondaryLocaleCodesForContent(?string $storedContentLocale): array
    {
        $primary = self::primaryLocaleForContent($storedContentLocale);
        $codes = [];
        foreach (self::all() as $row) {
            if ($row['code'] !== $primary) {
                $codes[] = $row['code'];
            }
        }

        return $codes;
    }

    /**
     * @return array<string, array{code: string, label: string, native_label: string, rtl: bool}>
     */
    public static function byCode(): array
    {
        $map = [];
        foreach (self::all() as $row) {
            $map[$row['code']] = $row;
        }

        return $map;
    }

    public static function isRtl(string $locale): bool
    {
        return (bool) (self::byCode()[strtolower($locale)]['rtl'] ?? false);
    }

    /**
     * @param  array<int, mixed>  $list
     * @return array<int, array{code: string, label: string, native_label: string, rtl: bool}>
     */
    public static function normalizeList(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }
            $code = isset($item['code']) ? strtolower(trim((string) $item['code'])) : '';
            if ($code === '' || ! preg_match('/^[a-z]{2}(-[a-z0-9]+)?$/i', $code)) {
                continue;
            }
            $out[] = [
                'code' => $code,
                'label' => isset($item['label']) ? (string) $item['label'] : strtoupper($code),
                'native_label' => isset($item['native_label']) ? (string) $item['native_label'] : (isset($item['label']) ? (string) $item['label'] : strtoupper($code)),
                'rtl' => ! empty($item['rtl']),
            ];
        }

        if ($out === []) {
            return self::defaults();
        }

        // Unique by code (first wins)
        $seen = [];
        $unique = [];
        foreach ($out as $row) {
            if (isset($seen[$row['code']])) {
                continue;
            }
            $seen[$row['code']] = true;
            $unique[] = $row;
        }

        return array_values($unique);
    }

    /**
     * @return array<int, array{code: string, label: string, native_label: string, rtl: bool}>
     */
    public static function defaults(): array
    {
        return [
            [
                'code' => 'en',
                'label' => 'English',
                'native_label' => 'English',
                'rtl' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadRaw(): array
    {
        return ProjectSettingsStore::load();
    }
}
