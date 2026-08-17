<?php

namespace App\Support;

use App\Models\ProjectSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Shared read/write for operator project settings (Appearance + Settings).
 * Source of truth is the `project_settings` table (JSON payload).
 * Legacy `storage/app/private/project-settings.json` can be imported with `php artisan settings:import-file`.
 */
final class ProjectSettingsStore
{
    public const LEGACY_FILE_PATH = 'project-settings.json';

    /**
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        if (! Schema::hasTable('project_settings')) {
            return [];
        }

        $row = ProjectSetting::query()->find(ProjectSetting::SINGLETON_ID);
        $payload = $row?->payload;

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function save(array $settings): void
    {
        unset($settings['features']);

        $row = ProjectSetting::query()->find(ProjectSetting::SINGLETON_ID);
        if ($row === null) {
            $row = new ProjectSetting();
            $row->id = ProjectSetting::SINGLETON_ID;
        }
        $row->payload = $settings;
        $row->save();
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
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

    /**
     * Copy legacy JSON file into the table. Returns true when a row was written.
     */
    public static function importFromLegacyFile(bool $overwrite = false): bool
    {
        $decoded = self::readLegacyFile();
        if ($decoded === null) {
            return false;
        }

        if (! $overwrite && self::load() !== []) {
            return false;
        }

        self::save(self::rewritePublicAppUrls($decoded));

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function readLegacyFile(): ?array
    {
        if (! Storage::disk('local')->exists(self::LEGACY_FILE_PATH)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk('local')->get(self::LEGACY_FILE_PATH), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Point stored media URLs at the current APP_URL (local artisan → production API).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function rewritePublicAppUrls(array $settings): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '') {
            return $settings;
        }

        $from = [
            'http://127.0.0.1:8000',
            'http://localhost:8000',
            'https://127.0.0.1:8000',
            'https://localhost:8000',
        ];

        return self::replaceInPayload($settings, $from, $appUrl);
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $from
     * @return array<string, mixed>
     */
    private static function replaceInPayload(array $value, array $from, string $to): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::replaceInPayload($item, $from, $to);
                continue;
            }
            if (is_string($item) && $item !== '') {
                $value[$key] = str_replace($from, $to, $item);
            }
        }

        return $value;
    }
}
