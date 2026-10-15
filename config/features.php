<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled content / admin modules (config only)
    |--------------------------------------------------------------------------
    |
    | Forks toggle entire API surfaces by setting booleans below. Built-in
    | modules are NOT read from .env (backend remains the source of truth).
    | Values are exposed on GET /api/settings as `features` and enforced by
    | the `feature.module:*` middleware on API routes.
    |
    | Keys must stay in sync with App\Support\FeatureModules::builtInKeys().
    | Installed addon modules default off; enable them per deployment with
    | `addons` below, or pin one here by its module key (wins over `addons`).
    |
    */

    'modules' => [
        'projects' => true,
        'categories' => true,
        'skills' => true,
        'services' => true,
        'testimonials' => true,
        'blog' => true,
        'pages' => true,
        'forms' => true,
        'media' => true,
        'users' => true,
        'seo' => true,
        'overlays' => true,
    ],

    /*
    | Comma-separated addon module keys to enable on this deployment (e.g. "shop,booking").
    | Only installed addons (Composer package discovery) are recognised; see docs/ADDONS.md.
    */
    'addons' => env('FEATURE_ADDONS', ''),

];
