<?php

namespace App\Support;

/**
 * Canonical module toggles from config/features.php (no .env).
 */
final class FeatureModules
{
    /**
     * Module keys mirrored in backend/config/features.php `modules`.
     *
     * @return list<string>
     */
    public static function canonicalKeys(): array
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
        ];
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
        foreach (self::canonicalKeys() as $key) {
            $out[$key] = self::coerceBool($configured[$key] ?? true);
        }

        return $out;
    }

    public static function enabled(string $module): bool
    {
        return self::all()[$module] ?? false;
    }

    private static function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
