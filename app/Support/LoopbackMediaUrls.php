<?php

namespace App\Support;

/**
 * Builder/settings JSON keeps absolute media URLs (`{APP_URL}/storage/...`) chosen in the media picker.
 * Content created on a local backend and imported elsewhere still points at 127.0.0.1 / localhost; this
 * maps those `/storage/` URLs onto the current APP_URL when it is not itself a loopback host.
 */
final class LoopbackMediaUrls
{
    private const PATTERN = '#https?://(?:127\.0\.0\.1|localhost|\[::1\])(?::\d+)?(?=/storage/)#i';

    public static function rewrite(mixed $value): mixed
    {
        $target = self::targetOrigin();
        if ($target === null) {
            return $value;
        }

        return self::rewriteWith($value, $target);
    }

    private static function rewriteWith(mixed $value, string $target): mixed
    {
        if (is_string($value)) {
            return $value === '' ? $value : (preg_replace(self::PATTERN, $target, $value) ?? $value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::rewriteWith($item, $target);
            }
        }

        return $value;
    }

    private static function targetOrigin(): ?string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $host = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
        if ($host === '' || in_array($host, ['127.0.0.1', 'localhost', '[::1]', '::1'], true)) {
            return null;
        }

        return $appUrl;
    }
}
