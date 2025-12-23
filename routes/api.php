<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn () => request()->user());
});

