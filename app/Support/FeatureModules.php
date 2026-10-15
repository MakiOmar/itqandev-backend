<?php

namespace App\Support;

use App\Addons\AddonRegistry;

/**
 * Module toggles from config/features.php. Built-in modules default on; installed addon modules default off.
 */
final class FeatureModules
{
    /**
     * Module keys mirrored in backend/config/features.php `modules`.
     *
     * @return list<string>
     */
    public static function builtInKeys(): array
    {
        return [
            'projects',
            'categories',
            'skills',
            'services',
            'testimonials',
            'blog',
            'pages',
            'forms',
            'media',
            'users',
            'seo',
            'overlays',
        ];
    }

    /**
     * Built-in keys plus the module key of every installed addon.
     *
     * @return list<string>
     */
    public static function canonicalKeys(): array
    {
        return array_values(array_unique(array_merge(self::builtInKeys(), AddonRegistry::moduleKeys())));
    }

    /**
     * Effective enabled flags for API payloads (unknown keys ignored).
     *
     * @return array<string, bool>
     */
    public static function all(): array
    {
        /** @var array<string, mixed> $configured */
        $configured = config('features.modules', []);
        $out = [];
        foreach (self::builtInKeys() as $key) {
            $out[$key] = self::coerceBool($configured[$key] ?? true);
        }
        $addonsOn = self::enabledAddonKeys();
        foreach (AddonRegistry::moduleKeys() as $key) {
            $out[$key] = array_key_exists($key, $configured)
                ? self::coerceBool($configured[$key])
                : in_array($key, $addonsOn, true);
        }

        return $out;
    }

    public static function enabled(string $module): bool
    {
        return self::all()[$module] ?? false;
    }

    /** @return list<string> */
    private static function enabledAddonKeys(): array
    {
        $raw = config('features.addons', []);
        $list = is_string($raw) ? explode(',', $raw) : (is_array($raw) ? $raw : []);

        return array_values(array_filter(array_map(
            static fn ($k) => strtolower(trim((string) $k)),
            $list,
        )));
    }

    private static function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
