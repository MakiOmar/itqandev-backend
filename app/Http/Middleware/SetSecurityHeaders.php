<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // JSON/API responses should not be framed (clickjacking).
        if ($request->is('api/*')) {
            $response->headers->set('X-Frame-Options', 'DENY');
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        $connectSrc = $this->connectSrcDirective();
        $csp = "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' data:; " .
            "connect-src {$connectSrc}; " .
            "frame-ancestors 'self';";
        $response->headers->set('Content-Security-Policy', $csp);

        $hsts = (int) config('app.hsts_max_age', 0);
        if ($hsts > 0 && $this->isHttps($request)) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=' . $hsts . '; includeSubDomains'
            );
        }

        return $response;
    }

    private function isHttps(Request $request): bool
    {
        return $request->secure()
            || $request->header('X-Forwarded-Proto') === 'https';
    }

    /**
     * @return string Space-separated CSP connect-src tokens
     */
    private function connectSrcDirective(): string
    {
        $parts = ["'self'"];

        $appUrl = (string) config('app.url', '');
        if ($appUrl !== '') {
            $parsed = parse_url($appUrl);
            if (! empty($parsed['host'])) {
                $scheme = ($parsed['scheme'] ?? 'https') . '://';
                $origin = $scheme . $parsed['host'];
                if (! empty($parsed['port'])) {
                    $origin .= ':' . $parsed['port'];
                }
                $parts[] = $origin;
            }
        }

        foreach (config('cors.allowed_origins', []) as $origin) {
            if (is_string($origin) && $origin !== '') {
                $parts[] = $origin;
            }
        }

        return implode(' ', array_unique($parts));
    }
}
