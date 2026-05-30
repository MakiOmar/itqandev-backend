<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets Cache-Control (and related) headers for API and complements Apache rules on static files.
 */
class SetHttpCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');

            return $response;
        }

        if ($this->isDiagnosticRoute($request)) {
            $this->setNoCache($response);

            return $response;
        }

        if ($request->is('api/public/*') && ! $this->shouldBypassPublicCache($request)) {
            $this->setPublicMarketingApiCache($response);

            return $response;
        }

        if ($request->is('api/*')) {
            $this->setPrivateApiCache($response);
        }

        return $response;
    }

    private function isDiagnosticRoute(Request $request): bool
    {
        return $request->is(
            'api/public/ping',
            'api/health',
            'up',
        );
    }

    private function shouldBypassPublicCache(Request $request): bool
    {
        if ($request->headers->has('Authorization')) {
            return true;
        }

        $user = $request->user();

        return $user !== null;
    }

    private function setNoCache(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->remove('Expires');
    }

    private function setPrivateApiCache(Response $response): void
    {
        $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->remove('Expires');
    }

    private function setPublicMarketingApiCache(Response $response): void
    {
        $maxAge = max(0, (int) config('http-cache.public_api_max_age', 60));
        $sMaxAge = max(0, (int) config('http-cache.public_api_s_maxage', 300));
        $swr = max(0, (int) config('http-cache.public_api_stale_while_revalidate', 86400));

        $parts = ['public', 'max-age='.$maxAge];
        if ($sMaxAge > 0) {
            $parts[] = 's-maxage='.$sMaxAge;
        }
        if ($swr > 0) {
            $parts[] = 'stale-while-revalidate='.$swr;
        }

        $response->headers->set('Cache-Control', implode(', ', $parts));
        $response->headers->set('Vary', 'Accept-Language, X-Content-Locale', false);
    }
}
