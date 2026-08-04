<?php

namespace App\Services\Appearance;

use App\Support\SiteLanguages;

/**
 * Normalize / present optional floating icons around the homepage hero image.
 *
 * @phpstan-type FloatingIcon array{id: string, enabled: bool, media_id: int|null, motion: string, x: float, y: float, size: int}
 * @phpstan-type FloatingIconPublic array{id: string, url: string, alt: ?string, motion: string, x: float, y: float, size: int}
 */
final class HeroFloatingIcons
{
    public const MOTIONS = ['rotate', 'diagonal', 'bounce'];

    public const MAX_ICONS = 24;

    /**
     * @return list<FloatingIcon>
     */
    public static function normalize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (count($out) >= self::MAX_ICONS) {
                break;
            }

            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = 'icon_'.bin2hex(random_bytes(4));
            }

            $mediaId = $row['media_id'] ?? null;
            if (is_string($mediaId) && ctype_digit(trim($mediaId))) {
                $mediaId = (int) $mediaId;
            } elseif (! is_int($mediaId)) {
                $mediaId = null;
            }
            if ($mediaId !== null && $mediaId <= 0) {
                $mediaId = null;
            }

            $motion = strtolower(trim((string) ($row['motion'] ?? 'rotate')));
            if (! in_array($motion, self::MOTIONS, true)) {
                $motion = 'rotate';
            }

            $x = self::clampPercent($row['x'] ?? 10);
            $y = self::clampPercent($row['y'] ?? 20);
            $size = (int) ($row['size'] ?? 56);
            $size = max(32, min(120, $size));

            $out[] = [
                'id' => $id,
                'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'media_id' => $mediaId,
                'motion' => $motion,
                'x' => $x,
                'y' => $y,
                'size' => $size,
            ];
        }

        return $out;
    }

    /**
     * Resolve media urls/alts for enabled icons (skips rows without media).
     *
     * @param  list<array<string, mixed>>  $icons
     * @return list<FloatingIconPublic>
     */
    public static function presentPublic(array $icons, ?string $locale = null): array
    {
        $defaultLocale = SiteLanguages::defaultCode();
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : $defaultLocale;

        $normalized = self::normalize($icons);
        $out = [];
        foreach ($normalized as $icon) {
            if (! ($icon['enabled'] ?? false)) {
                continue;
            }
            $mediaId = $icon['media_id'] ?? null;
            if (! is_int($mediaId) || $mediaId <= 0) {
                continue;
            }
            $resolved = AppearanceMediaResolver::resolve($mediaId, $locale, $defaultLocale);
            if ($resolved['url'] === '') {
                continue;
            }
            $out[] = [
                'id' => $icon['id'],
                'url' => $resolved['url'],
                'alt' => $resolved['alt'],
                'motion' => $icon['motion'],
                'x' => $icon['x'],
                'y' => $icon['y'],
                'size' => $icon['size'],
            ];
        }

        return $out;
    }

    private static function clampPercent(mixed $value): float
    {
        $n = is_numeric($value) ? (float) $value : 0.0;
        if ($n < 0) {
            return 0.0;
        }
        if ($n > 100) {
            return 100.0;
        }

        return round($n, 2);
    }
}
