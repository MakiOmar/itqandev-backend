<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Shared read/write for storage/app/project-settings.json (Appearance + Settings).
 */
final class ProjectSettingsStore
{
    public const FILE_PATH = 'project-settings.json';

    /**
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        if (! Storage::disk('local')->exists(self::FILE_PATH)) {
            return [];
        }

        $content = Storage::disk('local')->get(self::FILE_PATH);
        $decoded = json_decode($content ?? '', true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function save(array $settings): void
    {
        Storage::disk('local')->put(
            self::FILE_PATH,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Merge keys into stored settings and persist.
     *
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed> Full settings after merge
     */
    public static function merge(array $patch): array
    {
        $settings = self::load();
        foreach ($patch as $key => $value) {
            $settings[$key] = $value;
        }
        self::save($settings);

        return $settings;
    }
}
