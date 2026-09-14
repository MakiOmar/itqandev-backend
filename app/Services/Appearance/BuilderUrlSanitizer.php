<?php

namespace App\Services\Appearance;

/**
 * Allow only http(s), site-relative, or in-page hash URLs on builder attributes.
 */
final class BuilderUrlSanitizer
{
    public static function sanitize(mixed $value, int $max = 2048): string
    {
        $url = trim((string) $value);
        if ($url === '' || mb_strlen($url) > $max) {
            return '';
        }
        if (preg_match('#^(javascript|data|vbscript|file):#i', $url) === 1) {
            return '';
        }
        if ($url[0] === '#') {
            return $url;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return '';
    }
}
