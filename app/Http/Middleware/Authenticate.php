<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API, return null to avoid redirecting to a non-existent login route
        return null;
    }

    /**
     * Handle unauthenticated requests.
     */
    protected function unauthenticated($request, array $guards)
    {
        // Always return JSON for API unauthenticated responses
        abort(response()->json(['message' => 'Unauthenticated'], Response::HTTP_UNAUTHORIZED));
    }
}

