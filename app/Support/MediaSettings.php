<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Media pipeline options stored in project-settings.json (same file as SettingsController).
 */
final class MediaSettings
{
    private const SETTINGS_FILE_PATH = 'project-settings.json';

    public static function convertToWebpEnabled(): bool
    {
        $stored = self::readStoredBoolean('media_convert_to_webp');
        if ($stored !== null) {
            return $stored;
        }

        return (bool) config('media.convert_to_webp', true);
    }

    private static function readStoredBoolean(string $key): ?bool
    {
        $decoded = self::loadRaw();
        if (! array_key_exists($key, $decoded)) {
            return null;
        }

        return self::toBool($decoded[$key]);
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

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
