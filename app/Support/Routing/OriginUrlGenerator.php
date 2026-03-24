<?php

namespace App\Support\Routing;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;

class OriginUrlGenerator
{
    public function appOrigin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function overlayOrigin(): string
    {
        return rtrim((string) config('app.overlay_url', config('app.url')), '/');
    }

    public function overlayRoute(string $name, array $parameters = []): string
    {
        return $this->overlayPath(route($name, $parameters, absolute: false));
    }

    public function temporarySignedOverlayRoute(
        string $name,
        DateTimeInterface $expiration,
        array $parameters = [],
    ): string {
        return $this->overlayPath(
            URL::temporarySignedRoute($name, $expiration, $parameters, absolute: false),
        );
    }

    public function signedOverlayRoute(string $name, array $parameters = []): string
    {
        return $this->overlayPath(
            URL::signedRoute($name, $parameters, absolute: false),
        );
    }

    private function overlayPath(string $path): string
    {
        return $this->overlayOrigin().'/'.ltrim($path, '/');
    }
}
