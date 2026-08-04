<?php

namespace App\Services\Appearance;

/**
 * Clamp hero particle appearance settings (1–100 scales + optional hex color).
 */
final class HeroParticlesSettings
{
    public const SCALE_MIN = 1;

    public const SCALE_MAX = 100;

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function normalizeInto(array $settings): array
    {
        $settings['particles_enabled'] = filter_var($settings['particles_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $settings['particles_density'] = self::clampScale($settings['particles_density'] ?? 50, 50);
        $settings['particles_speed'] = self::clampScale($settings['particles_speed'] ?? 40, 40);
        $settings['particles_opacity'] = self::clampScale($settings['particles_opacity'] ?? 55, 55);
        $settings['particles_size'] = self::clampScale($settings['particles_size'] ?? 40, 40);
        $settings['particles_color'] = self::normalizeColor($settings['particles_color'] ?? '');

        return $settings;
    }

    private static function clampScale(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }
        $n = (int) round((float) $value);

        return max(self::SCALE_MIN, min(self::SCALE_MAX, $n));
    }

    private static function normalizeColor(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) === 1) {
            return strtolower($v);
        }

        return '';
    }
}
