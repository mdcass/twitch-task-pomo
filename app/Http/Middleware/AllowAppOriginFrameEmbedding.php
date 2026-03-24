<?php

namespace App\Http\Middleware;

use App\Support\Routing\OriginUrlGenerator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowAppOriginFrameEmbedding
{
    public function __construct(
        private readonly OriginUrlGenerator $originUrlGenerator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $frameAncestors = array_values(array_unique([
            $this->originUrlGenerator->appOrigin(),
            $this->originUrlGenerator->overlayOrigin(),
        ]));

        $response->headers->remove('X-Frame-Options');
        $response->headers->set(
            'Content-Security-Policy',
            'frame-ancestors '.implode(' ', $frameAncestors),
            false,
        );

        return $response;
    }
}
