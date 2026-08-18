<?php

namespace App\Services\Appearance;

/**
 * Sanitize builder hide_on maps. Returns null when nothing is hidden (omit from JSON).
 *
 * @phpstan-type HideOn array{mobile: bool, tablet: bool, desktop: bool}
 */
final class LayoutHideOn
{
    /**
     * @return HideOn|null
     */
    public static function normalize(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $out = [
            'mobile' => filter_var($raw['mobile'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'tablet' => filter_var($raw['tablet'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'desktop' => filter_var($raw['desktop'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if (! $out['mobile'] && ! $out['tablet'] && ! $out['desktop']) {
            return null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function appendTo(array $node, mixed $raw): array
    {
        $hide = self::normalize($raw);
        if ($hide === null) {
            return $node;
        }
        $node['hide_on'] = $hide;

        return $node;
    }
}
