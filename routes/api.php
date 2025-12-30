<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);

Route::post('/auth/login', [AuthController::class, 'login']);

// Public preflight for media download (avoid auth blocking OPTIONS)
Route::options('/v1/media/{media}/download', function (\Illuminate\Http\Request $request, $media) {
    $origin = $request->headers->get('origin');
    $resp = response('', 204);
    if ($origin) {
        $resp->headers->set('Access-Control-Allow-Origin', $origin);
        $resp->headers->set('Access-Control-Allow-Credentials', 'true');
        $resp->headers->set('Access-Control-Allow-Headers', 'authorization, content-type, accept, origin, x-requested-with, range');
        $resp->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $resp->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        $resp->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $resp->headers->set('Access-Control-Max-Age', '0');
        $resp->headers->set('Vary', 'Origin');
    }
    return $resp;
})->where('media', '[0-9]+');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn () => request()->user());
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('v1')->name('v1.')->group(function () {
        // Bulk actions MUST come before apiResource routes to avoid conflicts
        Route::post('categories/bulk-delete', [\App\Http\Controllers\Api\CategoryController::class, 'bulkDelete']);
        Route::post('skills/bulk-delete', [\App\Http\Controllers\Api\SkillController::class, 'bulkDelete']);
        Route::post('testimonials/bulk-delete', [\App\Http\Controllers\Api\TestimonialController::class, 'bulkDelete']);
        Route::post('projects/bulk-delete', [\App\Http\Controllers\Api\ProjectController::class, 'bulkDelete']);

        // API Resources
        Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);
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
            Route::post('/upload', [\App\Http\Controllers\Api\MediaController::class, 'upload'])->middleware('large.uploads');
            
            // Update and delete media
            Route::put('/{media}', [\App\Http\Controllers\Api\MediaController::class, 'update'])->where('media', '[0-9]+');
            Route::delete('/{media}', [\App\Http\Controllers\Api\MediaController::class, 'destroy'])->where('media', '[0-9]+');
            
            // Bulk operations
            Route::post('/bulk-delete', [\App\Http\Controllers\Api\MediaController::class, 'bulkDelete']);
        Route::get('/bulk-download', [\App\Http\Controllers\Api\MediaController::class, 'bulkDownload']);
            Route::post('/move-to-folder', [\App\Http\Controllers\Api\MediaController::class, 'moveToFolder']);
            
            // Folders
            Route::get('/folders/list', [\App\Http\Controllers\Api\MediaController::class, 'getFolders']);
            Route::post('/folders', [\App\Http\Controllers\Api\MediaController::class, 'createFolder']);
            Route::put('/folders/{folder}', [\App\Http\Controllers\Api\MediaController::class, 'updateFolder']);
            Route::delete('/folders/{folder}', [\App\Http\Controllers\Api\MediaController::class, 'deleteFolder']);
        });

        // Legacy route for attaching media to models
        Route::post('media/{type}/{id}/{collection}', [\App\Http\Controllers\Api\MediaController::class, 'store']);
        Route::put('seo/{type}/{id}', [\App\Http\Controllers\Api\SeoMetaController::class, 'update']);
    });
});

