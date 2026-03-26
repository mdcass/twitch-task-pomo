<?php

namespace App\Services\Integrations;

use App\Enums\ExternalAuthProvider;
use App\Models\ProviderAuth;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SpotifyPlaybackService
{
    private const CACHE_TTL_SECONDS = 20;

    private const CURRENTLY_PLAYING_URL = 'https://api.spotify.com/v1/me/player/currently-playing';

    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    public function payloadForTeam(Team $team): array
    {
        $providerAuth = $team->currentProviderAuth(ExternalAuthProvider::Spotify);

        if (! $providerAuth instanceof ProviderAuth) {
            return $this->payloadForAuth($providerAuth);
        }

        if ($this->shouldBypassCacheFor($providerAuth)) {
            return $this->payloadForAuth($providerAuth);
        }

        $cacheKey = $this->cacheKeyFor($team, $providerAuth);
        $cachedPayload = Cache::get($cacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $refreshed = false;
        $payload = $this->payloadForAuth($providerAuth, refreshed: $refreshed);

        if (! $refreshed) {
            Cache::put($cacheKey, $payload, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $payload;
    }

    public function payloadForAuth(
        ?ProviderAuth $providerAuth,
        bool $retried = false,
        bool &$refreshed = false,
    ): array {
        if (! $providerAuth instanceof ProviderAuth) {
            return $this->errorPayload('Connect Spotify to enable this widget.');
        }

        $response = Http::acceptJson()
            ->withToken((string) $providerAuth->access_token)
            ->get(self::CURRENTLY_PLAYING_URL);

        if ($response->status() === 401 && ! $retried && $this->refreshAccessToken($providerAuth)) {
            $refreshed = true;

            return $this->payloadForAuth($providerAuth->fresh(), true, $refreshed);
        }

        if ($response->status() === 204) {
            return $this->idlePayload('Nothing playing right now.');
        }

        if ($response->failed()) {
            return $this->errorPayload('Spotify playback is unavailable right now.');
        }

        $payload = $response->json();
        $item = is_array($payload) ? ($payload['item'] ?? null) : null;
        $isPlaying = is_array($payload) ? (bool) ($payload['is_playing'] ?? false) : false;

        if (! is_array($item) || ! $isPlaying) {
            return $this->idlePayload('Playback paused or idle.');
        }

        $artists = collect($item['artists'] ?? [])
            ->map(static fn (mixed $artist): ?string => is_array($artist) ? ($artist['name'] ?? null) : null)
            ->filter()
            ->values()
            ->all();

        return [
            'status' => 'playing',
            'message' => 'Currently playing from Spotify.',
            'track' => [
                'title' => (string) ($item['name'] ?? 'Unknown track'),
                'artists' => $artists,
                'artists_label' => $artists === [] ? 'Unknown artist' : implode(', ', $artists),
                'album_art_url' => Arr::get($item, 'album.images.0.url'),
            ],
            'source' => [
                'display_name' => $this->displayNameFor($providerAuth),
                'provider_user_id' => $providerAuth->provider_user_id,
            ],
        ];
    }

    private function shouldBypassCacheFor(ProviderAuth $providerAuth): bool
    {
        return $providerAuth->token_expires_at?->isPast() ?? false;
    }

    private function cacheKeyFor(Team $team, ProviderAuth $providerAuth): string
    {
        $tokenVersion = $providerAuth->token_expires_at?->getTimestamp() ?? 'none';

        return sprintf(
            'spotify-playback:team:%d:provider:%s:token:%s',
            $team->getKey(),
            ExternalAuthProvider::Spotify->value,
            $tokenVersion,
        );
    }

    private function refreshAccessToken(ProviderAuth $providerAuth): bool
    {
        if (! filled($providerAuth->refresh_token)) {
            return false;
        }

        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.spotify.client_id'),
                (string) config('services.spotify.client_secret'),
            )
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $providerAuth->refresh_token,
            ]);

        if ($response->failed()) {
            return false;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! filled($payload['access_token'] ?? null)) {
            return false;
        }

        $providerAuth->forceFill([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $providerAuth->refresh_token,
            'token_expires_at' => isset($payload['expires_in'])
                ? now()->addSeconds((int) $payload['expires_in'])
                : $providerAuth->token_expires_at,
        ])->save();

        return true;
    }

    private function displayNameFor(ProviderAuth $providerAuth): string
    {
        $profileDisplayName = $providerAuth->profile['display_name']
            ?? $providerAuth->profile['name']
            ?? null;

        if (is_string($profileDisplayName) && trim($profileDisplayName) !== '') {
            return trim($profileDisplayName);
        }

        if (filled($providerAuth->provider_email)) {
            return (string) $providerAuth->provider_email;
        }

        return $providerAuth->user?->email ?? 'Unknown account';
    }

    private function idlePayload(string $message): array
    {
        return [
            'status' => 'idle',
            'message' => $message,
            'track' => [
                'title' => null,
                'artists' => [],
                'artists_label' => null,
                'album_art_url' => null,
            ],
            'source' => null,
        ];
    }

    private function errorPayload(string $message): array
    {
        return [
            'status' => 'error',
            'message' => $message,
            'track' => [
                'title' => null,
                'artists' => [],
                'artists_label' => null,
                'album_art_url' => null,
            ],
            'source' => null,
        ];
    }
}
