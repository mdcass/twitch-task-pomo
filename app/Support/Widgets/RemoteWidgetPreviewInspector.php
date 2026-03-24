<?php

namespace App\Support\Widgets;

use App\Enums\Models\WidgetPreviewStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class RemoteWidgetPreviewInspector
{
    public function __construct(
        private readonly RemoteWidgetUrlGuard $remoteWidgetUrlGuard,
    ) {}

    public function inspect(string $url): RemoteWidgetPreviewResult
    {
        $checkedAt = now();

        try {
            $checkedUrl = $this->remoteWidgetUrlGuard->assertAllowed($url);
            [$response, $finalUrl] = $this->requestPreviewResponse($checkedUrl);

            if (! $response instanceof Response) {
                return new RemoteWidgetPreviewResult(
                    status: WidgetPreviewStatus::Unknown,
                    message: 'Preview could not be checked.',
                    checkedAt: $checkedAt,
                );
            }

            if ($response->status() >= 400) {
                return new RemoteWidgetPreviewResult(
                    status: WidgetPreviewStatus::Unknown,
                    message: 'Remote widget preview check returned an error response.',
                    checkedAt: $checkedAt,
                );
            }

            $frameResult = $this->determineFramePolicy($finalUrl, $response);

            if ($frameResult !== null) {
                return new RemoteWidgetPreviewResult(
                    status: WidgetPreviewStatus::Blocked,
                    message: $frameResult,
                    checkedAt: $checkedAt,
                );
            }

            return new RemoteWidgetPreviewResult(
                status: WidgetPreviewStatus::Ready,
                message: null,
                checkedAt: $checkedAt,
            );
        } catch (ConnectionException) {
            return new RemoteWidgetPreviewResult(
                status: WidgetPreviewStatus::Unknown,
                message: 'Preview check timed out.',
                checkedAt: $checkedAt,
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return new RemoteWidgetPreviewResult(
                status: WidgetPreviewStatus::Blocked,
                message: $exception->errors()['embed_url'][0] ?? 'Preview target is not allowed.',
                checkedAt: $checkedAt,
            );
        } catch (Throwable) {
            return new RemoteWidgetPreviewResult(
                status: WidgetPreviewStatus::Unknown,
                message: 'Preview check failed.',
                checkedAt: $checkedAt,
            );
        }
    }

    /**
     * @return array{0:?Response,1:string}
     */
    private function requestPreviewResponse(string $url, int $depth = 0): array
    {
        if ($depth > 3) {
            return [null, $url];
        }

        $response = Http::timeout(5)
            ->retry(1, 150, throw: false)
            ->withoutRedirecting()
            ->head($url);

        if ($response->status() === 405 || $response->status() === 501) {
            $response = Http::timeout(5)
                ->retry(1, 150, throw: false)
                ->withoutRedirecting()
                ->withHeaders([
                    'Range' => 'bytes=0-1024',
                ])
                ->get($url);
        }

        if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
            $location = $response->header('Location');

            if (! is_string($location) || trim($location) === '') {
                return [$response, $url];
            }

            $nextUrl = $this->resolveRedirectLocation($url, $location);
            $this->remoteWidgetUrlGuard->assertAllowed($nextUrl);

            return $this->requestPreviewResponse($nextUrl, $depth + 1);
        }

        return [$response, $url];
    }

    private function determineFramePolicy(string $url, Response $response): ?string
    {
        $xFrameOptions = strtolower($this->firstHeader($response, 'x-frame-options') ?? '');
        $appOrigin = rtrim((string) config('app.overlay_url', config('app.url')), '/');

        if ($xFrameOptions !== '') {
            if (Str::contains($xFrameOptions, 'deny')) {
                return 'Remote widget blocks iframe embedding.';
            }

            if (Str::contains($xFrameOptions, 'sameorigin') && ! $this->sameOrigin($url, $appOrigin)) {
                return 'Remote widget only allows same-origin iframe embedding.';
            }

            if (Str::startsWith($xFrameOptions, 'allow-from')) {
                if ($appOrigin === '' || ! Str::contains($xFrameOptions, strtolower($appOrigin))) {
                    return 'Remote widget does not allow this app to embed it.';
                }
            }
        }

        $csp = $this->firstHeader($response, 'content-security-policy');

        if ($csp === null) {
            return null;
        }

        $frameAncestors = $this->frameAncestorsDirective($csp);

        if ($frameAncestors === null) {
            return null;
        }

        if ($frameAncestors === "'none'") {
            return 'Remote widget disallows iframe embedding via Content Security Policy.';
        }

        if ($frameAncestors === '' || $frameAncestors === '*') {
            return null;
        }

        $tokens = preg_split('/\s+/', trim($frameAncestors)) ?: [];
        $appOriginLower = strtolower($appOrigin);

        foreach ($tokens as $token) {
            $token = strtolower(trim($token));

            if ($token === '' || $token === '*') {
                return null;
            }

            if ($token === "'self'" && $this->sameOrigin($url, $appOrigin)) {
                return null;
            }

            if ($token === 'https:' && Str::startsWith($appOriginLower, 'https://')) {
                return null;
            }

            if ($appOriginLower !== '' && Str::contains($appOriginLower, trim($token, '/'))) {
                return null;
            }
        }

        return 'Remote widget restricts iframe embedding via Content Security Policy.';
    }

    private function frameAncestorsDirective(string $policy): ?string
    {
        foreach (explode(';', $policy) as $directive) {
            $directive = trim($directive);

            if (Str::startsWith(strtolower($directive), 'frame-ancestors')) {
                return trim(Str::after($directive, ' '));
            }
        }

        return null;
    }

    private function firstHeader(Response $response, string $name): ?string
    {
        foreach ($response->headers() as $header => $values) {
            if (strtolower((string) $header) !== strtolower($name) || ! is_array($values) || $values === []) {
                continue;
            }

            return (string) $values[0];
        }

        return null;
    }

    private function sameOrigin(string $url, string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        $targetParts = parse_url($url);
        $originParts = parse_url($origin);

        if (! is_array($targetParts) || ! is_array($originParts)) {
            return false;
        }

        return ($targetParts['scheme'] ?? null) === ($originParts['scheme'] ?? null)
            && ($targetParts['host'] ?? null) === ($originParts['host'] ?? null)
            && (($targetParts['port'] ?? null) ?? $this->defaultPort($targetParts['scheme'] ?? null))
                === (($originParts['port'] ?? null) ?? $this->defaultPort($originParts['scheme'] ?? null));
    }

    private function defaultPort(?string $scheme): ?int
    {
        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }

    private function resolveRedirectLocation(string $currentUrl, string $location): string
    {
        if (filter_var($location, FILTER_VALIDATE_URL)) {
            return $location;
        }

        $currentParts = parse_url($currentUrl);

        if (! is_array($currentParts)) {
            return $location;
        }

        $scheme = (string) ($currentParts['scheme'] ?? 'https');
        $host = (string) ($currentParts['host'] ?? '');
        $port = isset($currentParts['port']) ? ':'.$currentParts['port'] : '';

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme.'://'.$host.$port.$location;
        }

        $path = (string) ($currentParts['path'] ?? '/');
        $basePath = str_contains($path, '/')
            ? preg_replace('#/[^/]*$#', '/', $path) ?: '/'
            : '/';

        return $scheme.'://'.$host.$port.rtrim($basePath, '/').'/'.ltrim($location, '/');
    }
}
