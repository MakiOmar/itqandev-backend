<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn () => request()->user());
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('v1')->name('v1.')->group(function () {
        Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);
        Route::apiResource('skills', \App\Http\Controllers\Api\SkillController::class);
        Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class);
    });
});

