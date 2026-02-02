<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\SetLocaleFromRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /**
         * Global middleware (runs for web + api)
         */
        $middleware->use([
            HandleCors::class,
            SetSecurityHeaders::class,
        ]);

        /**
         * WEB group
         */
        $middleware->group('web', [
            SetLocaleFromRequest::class,
            // (Laravel's default web middleware is handled internally;
            // you only add your custom stuff here)
        ]);

        /**
         * API group
         *
         * Ordering matters:
         * 1) Locale early (especially if it sets URL defaults)
         * 2) Sanctum stateful middleware ONLY if you use cookie-based SPA auth
         * 3) Throttle
         * 4) SubstituteBindings for implicit route model binding
         */
        $middleware->group('api', [
            SetLocaleFromRequest::class,

            // ✅ Keep this ONLY if you want Sanctum SPA cookie auth (XSRF-TOKEN, etc.)
            // If you're strictly using Bearer tokens, you can remove it.
            EnsureFrontendRequestsAreStateful::class,

            ThrottleRequests::class . ':api',
            SubstituteBindings::class, // ✅ REQUIRED for {model} binding
        ]);

        /**
         * Middleware aliases
         */
        $middleware->alias([
            'auth' => Authenticate::class,
            'verified' => EnsureEmailIsVerified::class,
            'large.uploads' => \App\Http\Middleware\HandleLargeFileUploads::class,
        ]);

        /**
         * IMPORTANT:
         * If SetLocaleFromRequest sets URL defaults (URL::defaults),
         * ensure it runs BEFORE SubstituteBindings.
         */
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetLocaleFromRequest::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
