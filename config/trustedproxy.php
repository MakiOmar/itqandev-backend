<?php

/**
 * Reverse proxies / CDN edges allowed to set X-Forwarded-* headers (read by Laravel's TrustProxies).
 * Without them, $request->ip() is the proxy address and every visitor shares one rate-limit bucket.
 *
 * TRUSTED_PROXIES: comma-separated IPs/CIDRs, or `*` to trust the calling proxy (only when the app is
 * unreachable except through that proxy). Empty = trust none.
 *
 * @see docs/CONFIGURATION.md
 */
return [

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
