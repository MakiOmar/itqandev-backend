<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleLargeFileUploads
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Increase PHP limits for file uploads
        if (function_exists('ini_set')) {
            $maxSize = config('media.max_file_size', 104857600); // 100MB default
            $maxSizeMB = (int) ($maxSize / 1024 / 1024);
            
            ini_set('upload_max_filesize', $maxSizeMB . 'M');
            ini_set('post_max_size', $maxSizeMB . 'M');
            ini_set('max_execution_time', '300');
            ini_set('max_input_time', '300');
            ini_set('memory_limit', '256M');
        }

        return $next($request);
    }
}

