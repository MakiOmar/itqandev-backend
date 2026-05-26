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
        $dbStatus = 'ok';
        $dbError = null;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'error';
            $dbError = app()->hasDebugModeEnabled() ? $e->getMessage() : 'unavailable';
        }

        $overallStatus = $dbStatus === 'ok' ? 'ok' : 'degraded';

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $overallStatus,
                'message' => 'Laravel API is reachable',
                'timestamp' => now()->toIso8601String(),
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
