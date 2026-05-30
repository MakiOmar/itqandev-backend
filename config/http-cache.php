<?php

/**
 * Browser / CDN Cache-Control for HTTP responses.
 *
 * @see App\Http\Middleware\SetHttpCacheHeaders
 * @see docs/CONFIGURATION.md (HTTP browser caching)
 */
return [

  /*
  |--------------------------------------------------------------------------
  | Public marketing API (GET /api/public/*)
  |--------------------------------------------------------------------------
  */
  'public_api_max_age' => (int) env('HTTP_CACHE_PUBLIC_API_MAX_AGE', 60),
  'public_api_s_maxage' => (int) env('HTTP_CACHE_PUBLIC_API_S_MAXAGE', 300),
  'public_api_stale_while_revalidate' => (int) env('HTTP_CACHE_PUBLIC_API_STALE_WHILE_REVALIDATE', 86400),

  /*
  |--------------------------------------------------------------------------
  | Static files under /storage (Apache serves existing files directly)
  |--------------------------------------------------------------------------
  */
  'storage_max_age' => (int) env('HTTP_CACHE_STORAGE_MAX_AGE', 31536000),

];
