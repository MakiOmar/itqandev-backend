<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class EnsureStatefulIfNoBearer
{
    public function handle(Request $request, Closure $next)
    {
        $auth = $request->headers->get('authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return $next($request);
        }

        return app(EnsureFrontendRequestsAreStateful::class)->handle($request, $next);
    }
}
