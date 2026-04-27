<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorize('manageSystemCache');

        $dbStatus = 'ok';
        $dbError = null;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'error';
            $dbError = app()->hasDebugModeEnabled() ? $e->getMessage() : 'unavailable';
        }

        $queue = config('queue.default');

        return response()->json([
            'app_env' => config('app.env'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => [
                'status' => $dbStatus,
                'connection' => config('database.default'),
                'error' => $dbError,
            ],
            'cache' => [
                'store' => config('cache.default'),
            ],
            'queue' => [
                'connection' => $queue,
            ],
        ]);
    }
}
