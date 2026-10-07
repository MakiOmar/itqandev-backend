<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects authenticated requests from deactivated accounts (covers session auth
 * and any token issued before the account was deactivated).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'This account is inactive.'], 403);
        }

        return $next($request);
    }
}
