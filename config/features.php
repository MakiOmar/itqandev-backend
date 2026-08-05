<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled content / admin modules (config only)
    |--------------------------------------------------------------------------
    |
    | Forks toggle entire API surfaces by setting booleans below. These are NOT
    | read from .env (backend remains the single source of truth via config).
    | Values are exposed on GET /api/settings as `features` and enforced by
    | the `feature.module:*` middleware on API routes.
    |
    | Keys must stay in sync with App\Support\FeatureModules::canonicalKeys().
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
    ],

];
