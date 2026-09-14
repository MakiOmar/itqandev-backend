<?php

namespace App\Support;

/**
 * Global color / type tokens for the page builder (project settings, not FEATURE_*).
 */
final class DesignKitResolver
{
    public const SETTINGS_KEY = 'design_kit';

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function normalize(mixed $raw, array $settings = []): array
    {
        $row = is_array($raw) ? $raw : [];
        $primary = self::hex($row['colors']['primary'] ?? $settings['primaryColor'] ?? $settings['primary_color'] ?? null)
            ?? '#0389a1';
        $secondary = self::hex($row['colors']['secondary'] ?? $settings['secondaryColor'] ?? $settings['secondary_color'] ?? null)
            ?? '#0ea5e9';
        $text = self::hex($row['colors']['text'] ?? null) ?? '#0f172a';
        $accent = self::hex($row['colors']['accent'] ?? null) ?? $secondary;
        $muted = self::hex($row['colors']['muted'] ?? null) ?? '#64748b';

        $custom = [];
        $rawCustom = is_array($row['colors']['custom'] ?? null) ? $row['colors']['custom'] : [];
        foreach ($rawCustom as $token) {
            if (! is_array($token)) {
                continue;
            }
            $id = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string) ($token['id'] ?? '')))) ?: null;
            $value = self::hex($token['value'] ?? null);
            if ($id === null || $value === null) {
                continue;
            }
            $custom[] = [
                'id' => $id,
                'name' => trim((string) ($token['name'] ?? $id)),
                'value' => $value,
            ];
            if (count($custom) >= 16) {
                break;
            }
        }

        return [
            'colors' => [
                'primary' => $primary,
                'secondary' => $secondary,
                'text' => $text,
                'accent' => $accent,
                'muted' => $muted,
                'custom' => $custom,
            ],
            'type_roles' => [
                'heading' => self::typeRole($row['type_roles']['heading'] ?? null, '700', '2rem', '1.2'),
                'body' => self::typeRole($row['type_roles']['body'] ?? null, '400', '1rem', '1.6'),
                'accent' => self::typeRole($row['type_roles']['accent'] ?? null, '600', '0.875rem', '1.4'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $kit
     */
    public static function cssVariables(array $kit): string
    {
        $c = is_array($kit['colors'] ?? null) ? $kit['colors'] : [];
        $lines = [
            '--kit-color-primary: '.($c['primary'] ?? '#0389a1').';',
            '--kit-color-secondary: '.($c['secondary'] ?? '#0ea5e9').';',
            '--kit-color-text: '.($c['text'] ?? '#0f172a').';',
            '--kit-color-accent: '.($c['accent'] ?? '#0ea5e9').';',
            '--kit-color-muted: '.($c['muted'] ?? '#64748b').';',
        ];
        foreach (is_array($c['custom'] ?? null) ? $c['custom'] : [] as $token) {
            if (! is_array($token)) {
                continue;
            }
            $id = preg_replace('/[^a-z0-9_-]/', '', (string) ($token['id'] ?? ''));
            $value = self::hex($token['value'] ?? null);
            if ($id && $value) {
                $lines[] = '--kit-color-'.$id.': '.$value.';';
            }
        }
        $roles = is_array($kit['type_roles'] ?? null) ? $kit['type_roles'] : [];
        foreach (['heading', 'body', 'accent'] as $role) {
            $r = is_array($roles[$role] ?? null) ? $roles[$role] : [];
            $lines[] = '--kit-type-'.$role.'-weight: '.($r['weight'] ?? '400').';';
            $lines[] = '--kit-type-'.$role.'-size: '.($r['size'] ?? '1rem').';';
            $lines[] = '--kit-type-'.$role.'-line: '.($r['line_height'] ?? '1.5').';';
        }

        return ':root{'.implode('', $lines).'}';
    }

    /**
     * @return array{weight: string, size: string, line_height: string}
     */
    private static function typeRole(mixed $raw, string $weight, string $size, string $line): array
    {
        $row = is_array($raw) ? $raw : [];
        $w = preg_replace('/\D/', '', (string) ($row['weight'] ?? $weight)) ?: $weight;
        $s = trim((string) ($row['size'] ?? $size));
        if ($s === '' || strlen($s) > 16) {
            $s = $size;
        }
        $lh = trim((string) ($row['line_height'] ?? $line));
        if ($lh === '' || strlen($lh) > 16) {
            $lh = $line;
        }

        return ['weight' => $w, 'size' => $s, 'line_height' => $lh];
    }

    private static function hex(mixed $value): ?string
    {
        $s = trim((string) $value);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $s) !== 1) {
            return null;
        }

        return strtolower($s);
    }
}
