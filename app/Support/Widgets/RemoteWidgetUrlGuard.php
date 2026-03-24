<?php

namespace App\Support\Widgets;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RemoteWidgetUrlGuard
{
    public function assertAllowed(string $url, string $field = 'embed_url'): string
    {
        $message = $this->check($url);

        if ($message !== null) {
            throw ValidationException::withMessages([$field => [$message]]);
        }

        return trim($url);
    }

    public function check(string $url): ?string
    {
        $validator = Validator::make(['embed_url' => $url], [
            'embed_url' => ['required', 'string', 'max:2048', 'url'],
        ], [
            'embed_url.url' => 'The embed URL must be a valid HTTPS URL.',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first('embed_url');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            return 'The embed URL must be a valid HTTPS URL.';
        }

        if ($host === '') {
            return 'The embed URL must include a valid host.';
        }

        if ($this->isProtectedApplicationHost($host)) {
            return 'The embed URL cannot point at this application.';
        }

        if ($this->isLocalHostname($host)) {
            return 'The embed URL cannot point at a local or private host.';
        }

        if ($this->isBlockedIp($host)) {
            return 'The embed URL cannot point at a local or private IP address.';
        }

        foreach ($this->resolvedAddressesFor($host) as $address) {
            if ($this->isBlockedIp($address)) {
                return 'The embed URL cannot resolve to a local or private IP address.';
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function resolvedAddressesFor(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if (! is_array($records) || $records === []) {
            return [];
        }

        return array_values(array_filter(array_map(static function (array $record): ?string {
            return Arr::get($record, 'ip') ?? Arr::get($record, 'ipv6');
        }, $records)));
    }

    private function isProtectedApplicationHost(string $host): bool
    {
        $protectedHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('app.overlay_url', config('app.url')), PHP_URL_HOST),
        ]);

        return in_array($host, array_map('strtolower', $protectedHosts), true);
    }

    private function isLocalHostname(string $host): bool
    {
        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || $host === 'host.docker.internal'
            || $host === '0.0.0.0';
    }

    private function isBlockedIp(string $address): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isBlockedIpv4($address);
        }

        return $this->isBlockedIpv6($address);
    }

    private function isBlockedIpv4(string $address): bool
    {
        $long = ip2long($address);

        if ($long === false) {
            return true;
        }

        return $this->ipv4InRange($long, '0.0.0.0', 8)
            || $this->ipv4InRange($long, '10.0.0.0', 8)
            || $this->ipv4InRange($long, '100.64.0.0', 10)
            || $this->ipv4InRange($long, '127.0.0.0', 8)
            || $this->ipv4InRange($long, '169.254.0.0', 16)
            || $this->ipv4InRange($long, '172.16.0.0', 12)
            || $this->ipv4InRange($long, '192.0.0.0', 24)
            || $this->ipv4InRange($long, '192.0.2.0', 24)
            || $this->ipv4InRange($long, '192.168.0.0', 16)
            || $this->ipv4InRange($long, '198.18.0.0', 15)
            || $this->ipv4InRange($long, '198.51.100.0', 24)
            || $this->ipv4InRange($long, '203.0.113.0', 24)
            || $this->ipv4InRange($long, '224.0.0.0', 4);
    }

    private function isBlockedIpv6(string $address): bool
    {
        $normalized = strtolower($address);

        return $normalized === '::1'
            || str_starts_with($normalized, 'fc')
            || str_starts_with($normalized, 'fd')
            || str_starts_with($normalized, 'fe8')
            || str_starts_with($normalized, 'fe9')
            || str_starts_with($normalized, 'fea')
            || str_starts_with($normalized, 'feb')
            || str_starts_with($normalized, '::')
            || str_starts_with($normalized, '2001:db8:');
    }

    private function ipv4InRange(int $value, string $network, int $prefix): bool
    {
        $networkLong = ip2long($network);

        if ($networkLong === false) {
            return false;
        }

        $mask = -1 << (32 - $prefix);

        return ($value & $mask) === ($networkLong & $mask);
    }
}
