<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MeController;
use App\Support\CorsAllowedOrigin;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok'])->middleware('throttle:health');

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

/** Marketing site (no auth). Module gates mirror config/features.php. */
Route::prefix('public')->middleware('throttle:api')->group(function () {
    Route::middleware('feature.module:projects')->group(function () {
        Route::get('projects', [\App\Http\Controllers\Api\PublicProjectController::class, 'index']);
        Route::get('projects/{slug}', [\App\Http\Controllers\Api\PublicProjectController::class, 'show'])
            ->where('slug', '[a-zA-Z0-9][a-zA-Z0-9-]*');
    });
    Route::middleware('feature.module:testimonials')->group(function () {
        Route::get('testimonials', [\App\Http\Controllers\Api\PublicTestimonialController::class, 'index']);
    });
    Route::middleware('feature.module:services')->group(function () {
        Route::get('services', [\App\Http\Controllers\Api\PublicServiceController::class, 'index']);
    });
    /** Branding + site_languages + module toggles for marketing (no auth). */
    Route::get('site-meta', [\App\Http\Controllers\Api\SettingsController::class, 'publicMeta']);
    /** Resolved nav tree for marketing header (locale query matches UI locale). */
    Route::get('menus/{slug}', [\App\Http\Controllers\Api\PublicMenuController::class, 'show'])
        ->where('slug', '[a-z0-9_-]+');
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
    Route::get('/me', [MeController::class, 'show']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Settings routes (outside v1 prefix to match frontend expectations)
    Route::get('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'index'])->middleware('throttle:api');
    Route::put('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'update'])->middleware('throttle:api');

    Route::prefix('v1')->name('v1.')->middleware('throttle:api')->group(function () {
        Route::middleware('feature.module:categories')->group(function () {
            Route::post('categories/bulk-delete', [\App\Http\Controllers\Api\CategoryController::class, 'bulkDelete'])->middleware('throttle:bulk');
            Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);
        });

        Route::middleware('feature.module:skills')->group(function () {
            Route::post('skills/bulk-delete', [\App\Http\Controllers\Api\SkillController::class, 'bulkDelete'])->middleware('throttle:bulk');
            Route::apiResource('skills', \App\Http\Controllers\Api\SkillController::class);
        });

        Route::middleware('feature.module:services')->group(function () {
            Route::post('services/bulk-delete', [\App\Http\Controllers\Api\ServiceController::class, 'bulkDelete'])->middleware('throttle:bulk');
            Route::apiResource('services', \App\Http\Controllers\Api\ServiceController::class);
        });

        Route::middleware('feature.module:testimonials')->group(function () {
            Route::post('testimonials/bulk-delete', [\App\Http\Controllers\Api\TestimonialController::class, 'bulkDelete'])->middleware('throttle:bulk');
            Route::apiResource('testimonials', \App\Http\Controllers\Api\TestimonialController::class);
        });

        Route::middleware('feature.module:projects')->group(function () {
            Route::post('projects/bulk-delete', [\App\Http\Controllers\Api\ProjectController::class, 'bulkDelete'])->middleware('throttle:bulk');
            Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class);
        });

        Route::middleware('feature.module:blog')->group(function () {
            Route::apiResource('blog-posts', \App\Http\Controllers\Api\BlogPostController::class);
        });

        Route::middleware('feature.module:users')->group(function () {
            Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
            Route::get('roles', fn () => \Spatie\Permission\Models\Role::select('id', 'name')->get());
        });

        Route::get('cache/status', [\App\Http\Controllers\Api\CacheController::class, 'status']);
        Route::post('cache/clear', [\App\Http\Controllers\Api\CacheController::class, 'clear']);

        Route::get('system/health', [\App\Http\Controllers\Api\SystemHealthController::class, 'show']);

        Route::middleware('feature.module:media')->group(function () {
            Route::prefix('media')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\MediaController::class, 'index']);
                Route::get('/statistics', [\App\Http\Controllers\Api\MediaController::class, 'statistics']);
                Route::get('/{media}', [\App\Http\Controllers\Api\MediaController::class, 'show'])->where('media', '[0-9]+');
                Route::get('/{media}/download-link', [\App\Http\Controllers\Api\MediaController::class, 'downloadLink'])->where('media', '[0-9]+');
                Route::get('/{media}/download', [\App\Http\Controllers\Api\MediaController::class, 'download'])->where('media', '[0-9]+');
                Route::post('/upload', [\App\Http\Controllers\Api\MediaController::class, 'upload'])->middleware(['large.uploads', 'throttle:uploads']);
                Route::put('/{media}', [\App\Http\Controllers\Api\MediaController::class, 'update'])->where('media', '[0-9]+');
                Route::delete('/{media}', [\App\Http\Controllers\Api\MediaController::class, 'destroy'])->where('media', '[0-9]+');
                Route::post('/bulk-delete', [\App\Http\Controllers\Api\MediaController::class, 'bulkDelete'])->middleware('throttle:bulk');
                Route::get('/bulk-download', [\App\Http\Controllers\Api\MediaController::class, 'bulkDownload'])
                    ->middleware('throttle:bulk');
                Route::post('/move-to-folder', [\App\Http\Controllers\Api\MediaController::class, 'moveToFolder']);
                Route::get('/folders/list', [\App\Http\Controllers\Api\MediaController::class, 'getFolders']);
                Route::post('/folders', [\App\Http\Controllers\Api\MediaController::class, 'createFolder']);
                Route::put('/folders/{folder}', [\App\Http\Controllers\Api\MediaController::class, 'updateFolder']);
                Route::delete('/folders/{folder}', [\App\Http\Controllers\Api\MediaController::class, 'deleteFolder']);
            });
            Route::post('media/{type}/{id}/{collection}', [\App\Http\Controllers\Api\MediaController::class, 'store'])->middleware('throttle:uploads');
        });

        Route::middleware('feature.module:seo')->group(function () {
            Route::put('seo/{type}/{id}', [\App\Http\Controllers\Api\SeoMetaController::class, 'update']);
        });

        Route::get('menus', [\App\Http\Controllers\Api\MenuController::class, 'index']);
        Route::post('menus', [\App\Http\Controllers\Api\MenuController::class, 'store']);
        Route::get('menus/{menu}', [\App\Http\Controllers\Api\MenuController::class, 'show']);
        Route::put('menus/{menu}', [\App\Http\Controllers\Api\MenuController::class, 'update']);
        Route::delete('menus/{menu}', [\App\Http\Controllers\Api\MenuController::class, 'destroy']);
        Route::post('menus/{menu}/items', [\App\Http\Controllers\Api\MenuItemController::class, 'store']);
        Route::put('menus/{menu}/items/reorder', [\App\Http\Controllers\Api\MenuItemController::class, 'reorder']);
        Route::put('menu-items/{menu_item}', [\App\Http\Controllers\Api\MenuItemController::class, 'update']);
        Route::delete('menu-items/{menu_item}', [\App\Http\Controllers\Api\MenuItemController::class, 'destroy']);
    });
});
