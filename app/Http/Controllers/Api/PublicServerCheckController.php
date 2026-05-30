<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Unauthenticated endpoint for verifying Laravel is reachable (local dev, SSR proxy, deployment).
 */
class PublicServerCheckController extends Controller
{
    public function ping(): JsonResponse
    {
        $started = microtime(true);
        $dbStatus = 'ok';
        $dbError = null;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'error';
            $dbError = app()->hasDebugModeEnabled() ? $e->getMessage() : 'unavailable';
        }

        $overallStatus = $dbStatus === 'ok' ? 'ok' : 'degraded';
        $serverMs = (int) round((microtime(true) - $started) * 1000);

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $overallStatus,
                'message' => 'Laravel API is reachable',
                'timestamp' => now()->toIso8601String(),
                'server_ms' => $serverMs,
                'app_env' => config('app.env'),
                'app_url' => config('app.url'),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'database' => [
                    'status' => $dbStatus,
                    'connection' => config('database.default'),
                    'error' => $dbError,
                ],
            ],
        ]);
    }
}
