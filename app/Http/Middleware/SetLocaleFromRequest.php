<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale') ?? $request->getPreferredLanguage(['ar', 'en']);

        if ($locale) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}

