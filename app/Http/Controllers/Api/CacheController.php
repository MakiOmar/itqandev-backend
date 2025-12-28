<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CacheController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'store' => config('cache.default'),
            'supports_tags' => method_exists(Cache::getStore(), 'supportsTags') ? Cache::getStore()->supportsTags() : false,
            'last_cleared_at' => Cache::get('cache:last_cleared_at'),
        ]);
    }

    public function clear(): JsonResponse
    {
        Cache::flush();
        $now = now()->toIso8601String();
        Cache::forever('cache:last_cleared_at', $now);

        return response()->json([
            'message' => 'Cache cleared',
            'last_cleared_at' => $now,
        ]);
    }
}

