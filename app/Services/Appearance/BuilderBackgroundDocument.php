<?php

namespace App\Services\Appearance;

/**
 * Sanitize layout-node `settings.background` (band / row / column / leaf).
 *
 * @phpstan-type BuilderBackground array{type: string, color?: string, gradient_from?: string, gradient_to?: string, gradient_angle?: int, image_url?: string, image_id?: int, overlay?: bool, overlay_color?: string, overlay_opacity?: int, image_size?: string, image_position?: string, image_repeat?: string, particles_density?: int, particles_speed?: int, particles_opacity?: int, particles_size?: int, particles_color?: string, rain_color?: string, rain_speed?: int, rain_density?: int, rain_direction?: string}
 */
final class BuilderBackgroundDocument
{
    public const TYPES = [
        'none',
        'color',
        'gradient',
        'image',
        'particles',
        'animated_rain',
    ];

    public const IMAGE_SIZES = ['cover', 'contain', 'auto'];

    public const IMAGE_REPEATS = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];

    public const RAIN_DIRECTIONS = ['down', 'up', 'both'];

    /**
     * @param  mixed  $raw
     * @return BuilderBackground|null  null when empty / invalid (omit key)
     */
    public static function normalize(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $typeRaw = strtolower(trim((string) ($raw['type'] ?? 'none')));
        $type = in_array($typeRaw, self::TYPES, true) ? $typeRaw : 'none';

        if ($type === 'none') {
            return ['type' => 'none'];
        }

        $out = ['type' => $type];

        if ($type === 'color') {
            $color = self::optionalHexColor($raw['color'] ?? null);
            if ($color !== null) {
                $out['color'] = $color;
            }

            return $out;
        }

        if ($type === 'gradient') {
            $out['gradient_from'] = self::optionalHexColor($raw['gradient_from'] ?? null) ?? '#0389a1';
            $out['gradient_to'] = self::optionalHexColor($raw['gradient_to'] ?? null) ?? '#0ea5e9';
            $out['gradient_angle'] = self::clampInt($raw['gradient_angle'] ?? 135, 0, 360, 135);

            return $out;
        }

        if ($type === 'image') {
            $url = self::optionalUrl($raw['image_url'] ?? null);
            if ($url !== null) {
                $out['image_url'] = $url;
            }
            $size = strtolower(trim((string) ($raw['image_size'] ?? 'cover')));
            $out['image_size'] = in_array($size, self::IMAGE_SIZES, true) ? $size : 'cover';
            $pos = trim((string) ($raw['image_position'] ?? 'center'));
            $out['image_position'] = $pos !== '' ? mb_substr($pos, 0, 64) : 'center';
            $repeat = strtolower(trim((string) ($raw['image_repeat'] ?? 'no-repeat')));
            $out['image_repeat'] = in_array($repeat, self::IMAGE_REPEATS, true) ? $repeat : 'no-repeat';
            if (is_numeric($raw['image_id'] ?? null) && (int) $raw['image_id'] > 0) {
                $out['image_id'] = (int) $raw['image_id'];
            }
            if (filter_var($raw['overlay'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $out['overlay'] = true;
                $out['overlay_color'] = self::optionalHexColor($raw['overlay_color'] ?? null) ?? '#000000';
                $out['overlay_opacity'] = self::clampInt($raw['overlay_opacity'] ?? 50, 0, 100, 50);
            }

            return $out;
        }

        if ($type === 'particles') {
            $out['particles_density'] = self::clampInt($raw['particles_density'] ?? 50, 10, 100, 50);
            $out['particles_speed'] = self::clampInt($raw['particles_speed'] ?? 40, 10, 100, 40);
            $out['particles_opacity'] = self::clampInt($raw['particles_opacity'] ?? 55, 10, 100, 55);
            $out['particles_size'] = self::clampInt($raw['particles_size'] ?? 40, 10, 100, 40);
            $color = self::optionalHexColor($raw['particles_color'] ?? null);
            if ($color !== null) {
                $out['particles_color'] = $color;
            }

            return $out;
        }

        // animated_rain
        $out['rain_speed'] = self::clampInt($raw['rain_speed'] ?? 45, 10, 100, 45);
        $out['rain_density'] = self::clampInt($raw['rain_density'] ?? 50, 10, 100, 50);
        $dir = strtolower(trim((string) ($raw['rain_direction'] ?? 'down')));
        $out['rain_direction'] = in_array($dir, self::RAIN_DIRECTIONS, true) ? $dir : 'down';
        $rainColor = self::optionalHexColor($raw['rain_color'] ?? null);
        if ($rainColor !== null) {
            $out['rain_color'] = $rainColor;
        }

        return $out;
    }

    /**
     * Keep layout-node settings; sanitize `background` when present.
     *
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    public static function normalizeLayoutSettings(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = $raw;
        if (array_key_exists('background', $out)) {
            $bg = self::normalize($out['background']);
            if ($bg === null) {
                unset($out['background']);
            } else {
                $out['background'] = $bg;
            }
        }
        if (array_key_exists('sticky', $out)) {
            $out['sticky'] = filter_var($out['sticky'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('sticky_offset', $out)) {
            $out['sticky_offset'] = max(0, min(240, (int) $out['sticky_offset']));
        }
        if (array_key_exists('shape_dividers', $out)) {
            $dividers = ShapeDividerDocument::normalize($out['shape_dividers']);
            if ($dividers === null) {
                unset($out['shape_dividers']);
            } else {
                $out['shape_dividers'] = $dividers;
            }
        }

        return $out;
    }

    private static function optionalHexColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v) !== 1) {
            return null;
        }

        return strtolower($v);
    }

    private static function optionalUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim($value);
        if ($v === '' || mb_strlen($v) > 2048) {
            return null;
        }
        // Allow absolute URLs and site-relative media paths.
        if (preg_match('#^(https?:)?//#i', $v) === 1 || str_starts_with($v, '/')) {
            return $v;
        }

        return null;
    }

    private static function clampInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }
        $n = (int) round((float) $value);

        return max($min, min($max, $n));
    }
}
