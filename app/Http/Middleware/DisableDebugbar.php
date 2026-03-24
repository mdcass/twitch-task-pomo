<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableDebugbar
{
    public function handle(Request $request, Closure $next): Response
    {
        config()->set('debugbar.enabled', false);

        if (
            class_exists(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class)
            && app()->bound(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class)
        ) {
            app(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class)->disable();
        }

        return $next($request);
    }
}
