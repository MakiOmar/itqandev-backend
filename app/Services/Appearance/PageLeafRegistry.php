<?php

namespace App\Services\Appearance;

/**
 * Resolve page-builder leaf definitions (atomic widgets + composite kits).
 */
final class PageLeafRegistry
{
    public const KIND_WIDGET = 'widget';

    public const KIND_KIT = 'kit';

    /**
     * Infer kind for legacy leaves that omit `kind`.
     */
    public static function inferKind(string $type, ?string $kind = null): ?string
    {
        $kind = strtolower(trim((string) $kind));
        if ($kind === self::KIND_WIDGET || $kind === self::KIND_KIT) {
            if ($kind === self::KIND_WIDGET && WidgetRegistry::has($type)) {
                return self::KIND_WIDGET;
            }
            if ($kind === self::KIND_KIT && KitRegistry::has($type)) {
                return self::KIND_KIT;
            }
            // Explicit kind that does not match — still try other registry.
        }

        if (KitRegistry::has($type)) {
            return self::KIND_KIT;
        }
        if (WidgetRegistry::has($type)) {
            return self::KIND_WIDGET;
        }

        return null;
    }

    public static function has(string $kind, string $type): bool
    {
        return match ($kind) {
            self::KIND_WIDGET => WidgetRegistry::has($type),
            self::KIND_KIT => KitRegistry::has($type),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(string $kind, string $type): array
    {
        return match ($kind) {
            self::KIND_WIDGET => WidgetRegistry::defaultSettings($type),
            self::KIND_KIT => KitRegistry::defaultSettings($type),
            default => [],
        };
    }

    public static function maxInstances(string $kind, string $type): ?int
    {
        return match ($kind) {
            self::KIND_WIDGET => WidgetRegistry::maxInstances($type),
            self::KIND_KIT => KitRegistry::maxInstances($type),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function translatableKeys(string $kind, string $type): array
    {
        return match ($kind) {
            self::KIND_WIDGET => WidgetRegistry::translatableKeys($type),
            self::KIND_KIT => KitRegistry::translatableKeys($type),
            default => [],
        };
    }

    /**
     * Count key for max_instances (kind-aware).
     */
    public static function countKey(string $kind, string $type): string
    {
        return $kind.':'.$type;
    }
}
