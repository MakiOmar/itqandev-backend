<?php

/**
 * Qwik dashboard HttpOnly session cookie (not Laravel's session cookie).
 * Same-origin /api requests forward this cookie; middleware promotes the token to Bearer.
 */
return [

    'cookie' => env('AUTH_SESSION_COOKIE', 'auth_session'),

];
