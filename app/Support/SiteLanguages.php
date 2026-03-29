<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Reads site language configuration from the same JSON file as SettingsController.
 */
final class SiteLanguages
{
    private const SETTINGS_FILE_PATH = 'project-settings.json';

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
        if (! Storage::disk('local')->exists(self::SETTINGS_FILE_PATH)) {
            return [];
        }
        $content = Storage::disk('local')->get(self::SETTINGS_FILE_PATH);
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
