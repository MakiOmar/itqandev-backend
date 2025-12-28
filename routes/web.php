<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Signed download route (used for temporary download links)
Route::get('/signed-media/{media}/download', [\App\Http\Controllers\Api\MediaController::class, 'download'])
    ->name('signed-media-download')
    ->middleware('signed');
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
});

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->middleware('auth')->name('logout');