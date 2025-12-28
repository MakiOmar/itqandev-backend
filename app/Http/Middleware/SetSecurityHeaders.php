<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // #region agent log
        $path = $request->path();
        if (str_starts_with($path, 'api/v1/media/')) {
            $this->debugLog('H6', 'SetSecurityHeaders', 'response headers', [
                'method' => $request->getMethod(),
                'path' => $path,
                'origin' => $request->headers->get('origin'),
                'status' => $response->getStatusCode(),
                'resp_headers' => [
                    'Access-Control-Allow-Origin' => $response->headers->get('Access-Control-Allow-Origin'),
                    'Access-Control-Allow-Credentials' => $response->headers->get('Access-Control-Allow-Credentials'),
                    'Access-Control-Expose-Headers' => $response->headers->get('Access-Control-Expose-Headers'),
                    'Access-Control-Allow-Headers' => $response->headers->get('Access-Control-Allow-Headers'),
                    'Access-Control-Allow-Methods' => $response->headers->get('Access-Control-Allow-Methods'),
                    'Cross-Origin-Resource-Policy' => $response->headers->get('Cross-Origin-Resource-Policy'),
                    'Vary' => $response->headers->get('Vary'),
                ],
            ]);
        }
        // #endregion

        return $response;
    }

    // #region agent log
    private function debugLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $payload = [
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ];

        $logPath = dirname(base_path()) . DIRECTORY_SEPARATOR . '.cursor' . DIRECTORY_SEPARATOR . 'debug.log';
        file_put_contents($logPath, json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }
    // #endregion
}

