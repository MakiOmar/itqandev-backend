<?php

namespace App\Http\Middleware;

use App\Support\FeatureModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abort API routes when a config-disabled module is requested (backend-controlled).
 */
class EnsureFeatureModuleEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! FeatureModules::enabled($module)) {
            abort(403, __('Module disabled.'));
        }

        return $next($request);
    }
}
