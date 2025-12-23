<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configure allowed origins for local dev (Nuxt:3000, Vite:5173, API:8000).
    | Adjust CORS_ALLOWED_ORIGINS in .env for production domains.
    |
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173'))),

    'allowed_origins_patterns' => [
        '#^https?://localhost(:[0-9]+)?$#',
        '#^https?://127\.0\.0\.1(:[0-9]+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 7200,

    'supports_credentials' => true,
];

