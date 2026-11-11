<?php

namespace App\Services\Appearance;

/**
 * Advanced → CSS ID / CSS classes on builder nodes. Values are rendered unescaped in selectors and
 * class/id attributes, so only plain identifiers survive. Mirrors website `builder-node-attrs.ts`.
 */
final class LayoutNodeAttributes
{
    public const MAX_CLASSES = 10;

    private const ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/';

    private const CLASS_PATTERN = '/^-?[A-Za-z_][A-Za-z0-9_-]{0,63}$/';

    /** Valid id without a leading `#`, or ''. */
    public static function normalizeId(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }
        $id = ltrim(trim($raw), '#');

        return preg_match(self::ID_PATTERN, $id) === 1 ? $id : '';
    }

    /** Space-separated valid class names (leading `.` dropped, deduplicated, at most 10), or ''. */
    public static function normalizeClasses(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }
        $out = [];
        foreach (preg_split('/\s+/', trim($raw)) ?: [] as $token) {
            $name = str_starts_with($token, '.') ? substr($token, 1) : $token;
            if (preg_match(self::CLASS_PATTERN, $name) === 1) {
                $out[$name] = true;
            }
            if (count($out) >= self::MAX_CLASSES) {
                break;
            }
        }

        return implode(' ', array_keys($out));
    }

    /**
     * Copies valid `css_id` / `css_classes` from the raw node; invalid or empty values are omitted.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function appendTo(array $node, array $raw): array
    {
        unset($node['css_id'], $node['css_classes']);
        $id = self::normalizeId($raw['css_id'] ?? null);
        if ($id !== '') {
            $node['css_id'] = $id;
        }
        $classes = self::normalizeClasses($raw['css_classes'] ?? null);
        if ($classes !== '') {
            $node['css_classes'] = $classes;
        }

        return $node;
    }
}
