<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip locale setting for health check routes
        if (in_array($request->path(), ['health', 'api/health', 'up'])) {
            return $next($request);
        }

        $locale = $request->header('X-Locale') ?? $request->getPreferredLanguage(['ar', 'en']);

        if ($locale) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}

