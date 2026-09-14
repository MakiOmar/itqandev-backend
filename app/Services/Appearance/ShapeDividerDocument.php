<?php

namespace App\Services\Appearance;

/**
 * Band Style → shape dividers (preset SVGs only; no operator-uploaded markup).
 */
final class ShapeDividerDocument
{
    public const PRESETS = ['none', 'wave', 'tilt', 'curve', 'triangle', 'mountains'];

    /**
     * @return array{top?: array<string, mixed>, bottom?: array<string, mixed>}|null
     */
    public static function normalize(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $out = [];
        foreach (['top', 'bottom'] as $edge) {
            $edgeRaw = $raw[$edge] ?? null;
            if (! is_array($edgeRaw)) {
                continue;
            }
            $preset = strtolower(trim((string) ($edgeRaw['preset'] ?? 'none')));
            if (! in_array($preset, self::PRESETS, true) || $preset === 'none') {
                continue;
            }
            $color = trim((string) ($edgeRaw['color'] ?? ''));
            if ($color !== '' && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color) !== 1) {
                $color = '';
            }
            $height = (int) ($edgeRaw['height'] ?? 80);
            $height = max(16, min(240, $height));
            $out[$edge] = [
                'preset' => $preset,
                'color' => $color !== '' ? strtolower($color) : '#0389a1',
                'flip' => filter_var($edgeRaw['flip'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'height' => $height,
            ];
        }

        return $out === [] ? null : $out;
    }
}
