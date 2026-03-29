<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors Laravel CORS config for responses HandleCors does not rewrite (e.g. BinaryFileResponse).
 * Never reflect arbitrary Origin; avoids credentialed + wildcard combinations.
 */
final class CorsAllowedOrigin
{
    public static function isAllowed(?string $origin): bool
    {
        if ($origin === null || $origin === '') {
            return false;
        }

        $cfg = config('cors');

        foreach ($cfg['allowed_origins'] ?? [] as $allowed) {
            if ($allowed !== '' && strcasecmp((string) $allowed, $origin) === 0) {
                return true;
            }
        }

        foreach ($cfg['allowed_origins_patterns'] ?? [] as $pattern) {
            if (@preg_match((string) $pattern, $origin) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function applyDownloadCors(?string $origin, Response $response): void
    {
        if (! self::isAllowed($origin)) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        $response->headers->set(
            'Access-Control-Allow-Headers',
            'authorization, content-type, accept, origin, x-requested-with, range'
        );
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $response->headers->set(
            'Access-Control-Max-Age',
            (string) (int) (config('cors.max_age') ?? 0)
        );
        $response->headers->set('Vary', 'Origin');
    }
}
