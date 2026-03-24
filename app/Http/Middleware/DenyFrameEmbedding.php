<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyFrameEmbedding
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'", false);
        $response->headers->set('X-Frame-Options', 'DENY', false);

        return $response;
    }
}
