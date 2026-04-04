<?php

use App\Http\Controllers\Api\AuthController;
use App\Support\CorsAllowedOrigin;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok'])->middleware('throttle:health');

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

/** Marketing site: published projects only (no auth). Respects X-Content-Locale for translated fields. */
Route::prefix('public')->middleware('throttle:api')->group(function () {
    Route::get('projects', [\App\Http\Controllers\Api\PublicProjectController::class, 'index']);
    Route::get('projects/{slug}', [\App\Http\Controllers\Api\PublicProjectController::class, 'show'])
        ->where('slug', '[a-zA-Z0-9][a-zA-Z0-9-]*');
    /** Branding + site_languages for marketing header (no auth; GET /settings is sanctum-only). */
    Route::get('site-meta', [\App\Http\Controllers\Api\SettingsController::class, 'publicMeta']);
});

// Public preflight for media download (avoid auth blocking OPTIONS)
Route::options('/v1/media/{media}/download', function (\Illuminate\Http\Request $request, $media) {
    $origin = $request->headers->get('origin');
    $resp = response('', 204);
    if ($origin && CorsAllowedOrigin::isAllowed($origin)) {
        $resp->headers->set('Access-Control-Allow-Origin', $origin);
        $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        $resp->headers->set('Access-Control-Allow-Headers', 'authorization, content-type, accept, origin, x-requested-with, range');
        $resp->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $resp->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        $resp->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $resp->headers->set('Access-Control-Max-Age', (string) (int) config('cors.max_age', 0));
        $resp->headers->set('Vary', 'Origin');
    }
    return $resp;
})->where('media', '[0-9]+');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn () => request()->user());
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Settings routes (outside v1 prefix to match frontend expectations)
    Route::get('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'index'])->middleware('throttle:api');
    Route::put('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'update'])->middleware('throttle:api');

    Route::prefix('v1')->name('v1.')->middleware('throttle:api')->group(function () {
        // Bulk actions MUST come before apiResource routes to avoid conflicts
        Route::post('categories/bulk-delete', [\App\Http\Controllers\Api\CategoryController::class, 'bulkDelete'])->middleware('throttle:bulk');
        Route::post('skills/bulk-delete', [\App\Http\Controllers\Api\SkillController::class, 'bulkDelete'])->middleware('throttle:bulk');
        Route::post('testimonials/bulk-delete', [\App\Http\Controllers\Api\TestimonialController::class, 'bulkDelete'])->middleware('throttle:bulk');
        Route::post('projects/bulk-delete', [\App\Http\Controllers\Api\ProjectController::class, 'bulkDelete'])->middleware('throttle:bulk');

        // API Resources
        
        Route::apiResource('skills', \App\Http\Controllers\Api\SkillController::class);
        Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class);
        Route::apiResource('testimonials', \App\Http\Controllers\Api\TestimonialController::class);
        Route::apiResource('blog-posts', \App\Http\Controllers\Api\BlogPostController::class);
        Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
        Route::get('roles', fn () => \Spatie\Permission\Models\Role::select('id', 'name')->get());

        // Cache status/clear
        Route::get('cache/status', [\App\Http\Controllers\Api\CacheController::class, 'status']);
        Route::post('cache/clear', [\App\Http\Controllers\Api\CacheController::class, 'clear']);
        
        // Media routes
        Route::prefix('media')->group(function () {
            // List and search media
            Route::get('/', [\App\Http\Controllers\Api\MediaController::class, 'index']);
            Route::get('/statistics', [\App\Http\Controllers\Api\MediaController::class, 'statistics']);
            Route::get('/{media}', [\App\Http\Controllers\Api\MediaController::class, 'show'])->where('media', '[0-9]+');
            Route::get('/{media}/download-link', [\App\Http\Controllers\Api\MediaController::class, 'downloadLink'])->where('media', '[0-9]+');
            Route::get('/{media}/download', [\App\Http\Controllers\Api\MediaController::class, 'download'])->where('media', '[0-9]+');
            
            // Upload media
            Route::post('/upload', [\App\Http\Controllers\Api\MediaController::class, 'upload'])->middleware(['large.uploads', 'throttle:uploads']);
            
            // Update and delete media
            Route::put('/{media}', [\App\Http\Controllers\Api\MediaController::class, 'update'])->where('media', '[0-9]+');
            Route::delete('/{media}', [\App\Http\Controllers\Api\MediaController::class, 'destroy'])->where('media', '[0-9]+');
            
            // Bulk operations
            Route::post('/bulk-delete', [\App\Http\Controllers\Api\MediaController::class, 'bulkDelete'])->middleware('throttle:bulk');
            Route::get('/bulk-download', [\App\Http\Controllers\Api\MediaController::class, 'bulkDownload'])
                ->middleware('throttle:bulk');
            Route::post('/move-to-folder', [\App\Http\Controllers\Api\MediaController::class, 'moveToFolder']);
            
            // Folders
            Route::get('/folders/list', [\App\Http\Controllers\Api\MediaController::class, 'getFolders']);
            Route::post('/folders', [\App\Http\Controllers\Api\MediaController::class, 'createFolder']);
            Route::put('/folders/{folder}', [\App\Http\Controllers\Api\MediaController::class, 'updateFolder']);
            Route::delete('/folders/{folder}', [\App\Http\Controllers\Api\MediaController::class, 'deleteFolder']);
        });
        Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);
        // Legacy route for attaching media to models
        Route::post('media/{type}/{id}/{collection}', [\App\Http\Controllers\Api\MediaController::class, 'store'])->middleware('throttle:uploads');
        Route::put('seo/{type}/{id}', [\App\Http\Controllers\Api\SeoMetaController::class, 'update']);
    });
});
