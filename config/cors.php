<?php

// Hostnames only (no scheme): e.g. itqandev.com,project.test — matches http(s)://host and any :port (Vite 5173).
$devVhostPatterns = [];
$devVhostHosts = env('CORS_DEV_HOST_NAMES');
if (is_string($devVhostHosts) && $devVhostHosts !== '') {
    foreach (array_map('trim', explode(',', $devVhostHosts)) as $host) {
        if ($host === '') {
            continue;
        }
        $escaped = preg_quote($host, '#');
        $devVhostPatterns[] = "#^https?://{$escaped}(:\d+)?$#";
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configure allowed origins for local dev (Nuxt:3000, Vite:5173, API:8000).
    | Use CORS_ALLOWED_ORIGINS for exact production origins.
    | Use CORS_DEV_HOST_NAMES for local WAMP/vhost hostnames mapped to localhost (scheme + optional port).
    |
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('CORS_ALLOWED_ORIGINS')
        ? array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS')))
        : [],

    'allowed_origins_patterns' => array_merge([
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
        '#^https?://::1(:\d+)?$#',
    ], $devVhostPatterns),

    'allowed_headers' => [
        '*',
        'Content-Type',
        'Authorization',
        'Accept',
        'Origin',
        'X-Requested-With',
        'Range',
    ],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => true,
];

