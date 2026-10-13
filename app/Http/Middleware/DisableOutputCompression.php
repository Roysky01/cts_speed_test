<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableOutputCompression
{
    /**
     * Keep download and upload bodies from being gzipped. Compressed random
     * payloads would report a faster link than the visitor actually has.
     */
    public function handle(Request $request, Closure $next): Response
    {
        @ini_set('zlib.output_compression', '0');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        return $next($request);
    }
}
