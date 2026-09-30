<?php

namespace App\Services\Appearance;

use App\Models\AppMedia;

/**
 * Sanitize `icon` control values before they are stored.
 *
 * Accepted shapes (anything else becomes ''):
 * - legacy name string, e.g. "star"
 * - bundled set icon: {library: "lucide", name, body, view_box} — body is inline SVG markup
 *   rendered on the public site, so it must pass a strict element/attribute allowlist
 * - uploaded image: {library: "svg", media_id} — URL is always re-read from our media table,
 *   so an icon can never point at a third-party host
 */
final class IconValueNormalizer
{
    public const LIBRARIES = ['lucide'];

    private const MAX_BODY_LENGTH = 20000;

    private const ALLOWED_TAGS = ['g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse'];

    private const ALLOWED_ATTRIBUTES = [
        'd', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit',
        'fill-rule', 'clip-rule', 'opacity', 'fill-opacity', 'stroke-opacity', 'transform',
        'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'width', 'height', 'points',
    ];

    /**
     * @return string|array<string, mixed>
     */
    public static function normalize(mixed $value): string|array
    {
        if (is_string($value)) {
            $name = strtolower(trim($value));

            return self::isValidName($name) ? $name : '';
        }
        if (! is_array($value)) {
            return '';
        }

        $library = strtolower(trim((string) ($value['library'] ?? '')));
        if ($library === 'svg') {
            return self::normalizeUpload($value);
        }
        if (in_array($library, self::LIBRARIES, true)) {
            return self::normalizeSetIcon($library, $value);
        }

        return '';
    }

    public static function isSafeBody(string $body): bool
    {
        if ($body === '' || strlen($body) > self::MAX_BODY_LENGTH) {
            return false;
        }

        $tagPattern = '/<\/?([a-zA-Z][\w-]*)((?:\s+[a-zA-Z][\w:-]*\s*=\s*"[^"<>]*")*)\s*\/?>/';
        $safe = true;
        $rest = preg_replace_callback($tagPattern, static function (array $m) use (&$safe): string {
            if (! in_array(strtolower($m[1]), self::ALLOWED_TAGS, true) || ! self::attributesAreSafe($m[2])) {
                $safe = false;
            }

            return '';
        }, $body);

        return $safe && is_string($rest) && trim($rest) === '';
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>|string
     */
    private static function normalizeSetIcon(string $library, array $value): array|string
    {
        $name = strtolower(trim((string) ($value['name'] ?? '')));
        $body = trim((string) ($value['body'] ?? ''));
        if (! self::isValidName($name) || ! self::isSafeBody($body)) {
            return '';
        }
        $viewBox = trim((string) ($value['view_box'] ?? ''));
        if (! preg_match('/^-?\d+(\.\d+)?( -?\d+(\.\d+)?){3}$/', $viewBox)) {
            $viewBox = '0 0 24 24';
        }

        return ['library' => $library, 'name' => $name, 'body' => $body, 'view_box' => $viewBox];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>|string
     */
    private static function normalizeUpload(array $value): array|string
    {
        $id = (int) ($value['media_id'] ?? 0);
        if ($id < 1) {
            return '';
        }
        /** @var AppMedia|null $media */
        $media = AppMedia::query()->find($id);
        if (! $media || ! str_starts_with(strtolower((string) $media->mime_type), 'image/')) {
            return '';
        }
        $url = (string) $media->getUrl();

        return $url === '' ? '' : ['library' => 'svg', 'media_id' => $id, 'url' => $url];
    }

    private static function attributesAreSafe(string $attributes): bool
    {
        preg_match_all('/([a-zA-Z][\w:-]*)\s*=\s*"([^"<>]*)"/', $attributes, $pairs, PREG_SET_ORDER);
        foreach ($pairs as [, $name, $val]) {
            if (! in_array(strtolower($name), self::ALLOWED_ATTRIBUTES, true)) {
                return false;
            }
            if (preg_match('/url\s*\(|javascript\s*:|expression\s*\(/i', $val)) {
                return false;
            }
        }

        return true;
    }

    private static function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name);
    }
}
