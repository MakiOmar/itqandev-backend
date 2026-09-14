<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Promote the Qwik HttpOnly auth_session token to Authorization when the browser
 * did not send a Bearer header. Existing API tokens are left unchanged.
 */
class AuthenticateFromAuthSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasBearerToken($request)) {
            return $next($request);
        }

        $token = $this->tokenFromAuthSessionCookie($request);
        if ($token !== null) {
            $request->headers->set('Authorization', 'Bearer '.$token);
            $request->server->set('HTTP_AUTHORIZATION', 'Bearer '.$token);
        }

        return $next($request);
    }

    private function hasBearerToken(Request $request): bool
    {
        $header = trim((string) $request->header('Authorization', ''));

        return $header !== '' && stripos($header, 'Bearer ') === 0 && trim(substr($header, 7)) !== '';
    }

    private function tokenFromAuthSessionCookie(Request $request): ?string
    {
        $name = (string) config('auth-session.cookie', 'auth_session');
        $raw = $request->cookie($name);
        if (! is_string($raw) || $raw === '') {
            $headerCookie = (string) $request->headers->get('cookie', '');
            if ($headerCookie !== '' && preg_match('/(?:^|;\s*)'.preg_quote($name, '/').'=([^;]*)/', $headerCookie, $m)) {
                $raw = urldecode($m[1]);
            }
        }
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $token = $decoded['token'] ?? null;
        if (! is_string($token) || $token === '' || $token === 'sanctum_cookie') {
            return null;
        }

        return $token;
    }
}
