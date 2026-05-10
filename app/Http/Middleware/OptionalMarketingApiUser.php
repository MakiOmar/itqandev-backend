<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a user from a Sanctum personal access token when no session user is present.
 * Pair with {@see EnsureStatefulIfNoBearer} so SPA sessions and API tokens both work on public marketing routes.
 */
class OptionalMarketingApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $plain = $request->bearerToken();
        if ($plain === null || $plain === '') {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($plain);
        if ($token && $token->tokenable) {
            $request->setUserResolver(static fn () => $token->tokenable);
        }

        return $next($request);
    }
}
