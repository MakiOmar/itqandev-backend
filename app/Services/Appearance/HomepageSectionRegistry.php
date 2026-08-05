<?php

namespace App\Services\Appearance;

/**
 * @deprecated Use {@see KitRegistry}. Kept as a thin alias for older imports/tests.
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, accept?: string, min?: int, max?: int}
 */
final class HomepageSectionRegistry
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return KitRegistry::all();
    }

    public static function has(string $type): bool
    {
        return KitRegistry::has($type);
    }

    /**
     * @return list<array{type: string, label: string, max_instances: int|null, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}>
     */
    public static function forAdmin(): array
    {
        // Preserve the historical payload shape (no kind/category required by old clients).
        $out = [];
        foreach (KitRegistry::forAdmin() as $row) {
            $out[] = [
                'type' => $row['type'],
                'label' => $row['label'],
                'max_instances' => $row['max_instances'],
                'default_settings' => $row['default_settings'],
                'settings_fields' => $row['settings_fields'],
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function translatableKeys(string $type): array
    {
        return KitRegistry::translatableKeys($type);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(string $type): array
    {
        return KitRegistry::defaultSettings($type);
    }

    public static function maxInstances(string $type): ?int
    {
        return KitRegistry::maxInstances($type);
    }
}
