<?php

use Illuminate\Support\Facades\Route;

Route::get('v1/sample-addon/ping', fn () => ['success' => true, 'data' => 'pong']);
