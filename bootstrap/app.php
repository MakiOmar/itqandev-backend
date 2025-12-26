<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\SetLocaleFromRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([HandleCors::class, SetSecurityHeaders::class]);

        $middleware->group('web', [
            SetLocaleFromRequest::class,
        ]);

        $middleware->group('api', [
            SetLocaleFromRequest::class,
            ThrottleRequests::class . ':api',
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'verified' => EnsureEmailIsVerified::class,
            'large.uploads' => \App\Http\Middleware\HandleLargeFileUploads::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
