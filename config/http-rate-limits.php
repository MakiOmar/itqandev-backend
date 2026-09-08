<?php

/**
 * API throttles (non-local). Read only via config('http-rate-limits.*').
 * env() lives here so php artisan config:cache still applies overrides.
 *
 * @see App\Providers\AppServiceProvider
 * @see docs/CONFIGURATION.md
 */
return [

    'authenticated_per_minute' => max(1, min((int) env('API_RATE_LIMIT_AUTHENTICATED_PER_MINUTE', 300), 5000)),

    'guest_per_minute' => max(1, min((int) env('API_RATE_LIMIT_GUEST_PER_MINUTE', 120), 1000)),

    'upload_per_minute' => max(1, min((int) env('UPLOAD_RATE_LIMIT_PER_MINUTE', 30), 200)),

    'bulk_per_minute' => max(1, min((int) env('BULK_RATE_LIMIT_PER_MINUTE', 10), 120)),

    'health_per_minute' => max(1, min((int) env('HEALTH_CHECK_RATE_LIMIT_PER_MINUTE', 200), 2000)),

    'form_submit_per_minute' => max(1, min((int) env('FORM_SUBMIT_RATE_LIMIT_PER_MINUTE', 8), 60)),

];
