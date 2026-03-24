<?php

namespace App\Actions\LocalWidgets;

use App\Enums\ExternalAuthProvider;
use App\Models\ProviderAuth;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use RuntimeException;

class SpotifyWidgetService
{
    private const CURRENTLY_PLAYING_URL = 'https://api.spotify.com/v1/me/player/currently-playing';

    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    public function __construct(
        private readonly SocialiteFactory $socialite,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.spotify.client_id'))
            && filled(config('services.spotify.client_secret'))
            && filled(config('services.spotify.redirect'));
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return ExternalAuthProvider::Spotify->authScopes();
    }

    public function redirect()
    {
        return $this->driver()
            ->with(['show_dialog' => 'true'])
            ->redirect();
    }

    public function connect(User $user): ProviderAuth
    {
        $providerUser = $this->driver()->user();
        $providerUserId = trim((string) $providerUser->getId());

        if ($providerUserId === '') {
            throw new RuntimeException('Spotify did not return a user identifier.');
        }

        $providerAuth = ProviderAuth::withTrashed()
            ->where('provider', ExternalAuthProvider::Spotify)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($providerAuth === null) {
            $providerAuth = new ProviderAuth();
        }

        $providerAuth->user()->associate($user);
        $providerAuth->forceFill($this->providerAuthAttributes($providerUserId, $providerUser));
        $providerAuth->save();

        if ($providerAuth->trashed()) {
            $providerAuth->restore();
        }

        ProviderAuth::query()
            ->where('user_id', $user->id)
            ->where('provider', ExternalAuthProvider::Spotify)
            ->whereKeyNot($providerAuth->id)
            ->get()
            ->each
            ->delete();

        Log::info('Persisted local Spotify provider auth.', [
            'user_id' => $user->id,
            'provider_auth_id' => $providerAuth->id,
            'provider_user_id' => $providerAuth->provider_user_id,
        ]);

        return $providerAuth->fresh();
    }

    public function disconnect(User $user): void
    {
        $user->providerAuths()
            ->where('provider', ExternalAuthProvider::Spotify)
            ->get()
            ->each
            ->delete();
    }

    public function currentUserConnection(User $user): ?ProviderAuth
    {
        return $user->providerAuths()
            ->where('provider', ExternalAuthProvider::Spotify)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->first();
    }

    public function latestConnection(): ?ProviderAuth
    {
        return ProviderAuth::query()
            ->where('provider', ExternalAuthProvider::Spotify)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPlaybackPayload(): array
    {
        $providerAuth = $this->latestConnection();

        if (! $providerAuth instanceof ProviderAuth) {
            Log::warning('Local Spotify widget could not resolve a latest provider auth.');

            return $this->errorPayload('Connect Spotify from the local widget launcher first.');
        }

        return $this->playbackPayload($providerAuth);
    }

    /**
     * @return array<string, mixed>
     */
    private function playbackPayload(ProviderAuth $providerAuth, bool $retried = false): array
    {
        if (! filled($providerAuth->access_token)) {
            return $this->revokeAndError($providerAuth);
        }

        $response = Http::acceptJson()
            ->withToken((string) $providerAuth->access_token)
            ->get(self::CURRENTLY_PLAYING_URL);

        Log::info('Fetched local Spotify playback response.', [
            'provider_auth_id' => $providerAuth->id,
            'provider_user_id' => $providerAuth->provider_user_id,
            'status' => $response->status(),
        ]);

        if ($response->status() === 401 && ! $retried) {
            Log::warning('Local Spotify playback returned unauthorized; attempting token refresh.', [
                'provider_auth_id' => $providerAuth->id,
                'provider_user_id' => $providerAuth->provider_user_id,
            ]);

            if ($this->refreshAccessToken($providerAuth)) {
                /** @var ProviderAuth|null $freshAuth */
                $freshAuth = $providerAuth->fresh();

                if ($freshAuth instanceof ProviderAuth) {
                    return $this->playbackPayload($freshAuth, true);
                }
            }

            return $this->revokeAndError($providerAuth);
        }

        if ($response->status() === 204) {
            return $this->idlePayload('Nothing playing right now.');
        }

        if ($response->failed()) {
            Log::warning('Local Spotify playback request failed.', [
                'provider_auth_id' => $providerAuth->id,
                'provider_user_id' => $providerAuth->provider_user_id,
                'status' => $response->status(),
            ]);

            return $this->errorPayload('Spotify preview is unavailable right now.');
        }

        $payload = $response->json();
        $item = is_array($payload) ? ($payload['item'] ?? null) : null;
        $isPlaying = is_array($payload) ? (bool) ($payload['is_playing'] ?? false) : false;

        if (! is_array($item) || ! $isPlaying) {
            return $this->idlePayload('Playback paused or idle.');
        }

        $artists = collect($item['artists'] ?? [])
            ->map(static fn (mixed $artist): ?string => is_array($artist) ? ($artist['name'] ?? null) : null)
            ->filter(static fn (?string $name): bool => filled($name))
            ->values()
            ->all();

        return [
            'status' => 'playing',
            'message' => 'Currently playing from the latest linked Spotify account.',
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

    private function refreshAccessToken(ProviderAuth $providerAuth): bool
    {
        if (! $this->isConfigured() || ! filled($providerAuth->refresh_token)) {
            Log::warning('Local Spotify token refresh skipped because config or refresh token is missing.', [
                'provider_auth_id' => $providerAuth->id,
                'provider_user_id' => $providerAuth->provider_user_id,
                'has_refresh_token' => filled($providerAuth->refresh_token),
                'configured' => $this->isConfigured(),
            ]);

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
            Log::error('Local Spotify token refresh failed.', [
                'provider_auth_id' => $providerAuth->id,
                'provider_user_id' => $providerAuth->provider_user_id,
                'status' => $response->status(),
            ]);

            return false;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! filled($payload['access_token'] ?? null)) {
            Log::error('Local Spotify token refresh returned an unexpected payload.', [
                'provider_auth_id' => $providerAuth->id,
                'provider_user_id' => $providerAuth->provider_user_id,
            ]);

            return false;
        }

        $providerAuth->forceFill([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $providerAuth->refresh_token,
            'token_expires_at' => isset($payload['expires_in'])
                ? Carbon::now()->addSeconds((int) $payload['expires_in'])
                : $providerAuth->token_expires_at,
        ])->save();

        Log::info('Local Spotify token refresh succeeded.', [
            'provider_auth_id' => $providerAuth->id,
            'provider_user_id' => $providerAuth->provider_user_id,
        ]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function providerAuthAttributes(string $providerUserId, SocialiteUser $providerUser): array
    {
        return [
            'provider' => ExternalAuthProvider::Spotify,
            'provider_user_id' => $providerUserId,
            'provider_email' => $this->normalizeEmail($providerUser->getEmail()),
            'avatar_url' => $this->normalizeAvatarUrl($providerUser->getAvatar()),
            'access_token' => $providerUser->token,
            'refresh_token' => $providerUser->refreshToken,
            'token_expires_at' => $providerUser->expiresIn !== null
                ? Carbon::now()->addSeconds((int) $providerUser->expiresIn)
                : null,
            'scopes' => $this->normalizeScopes($providerUser->approvedScopes ?? $this->scopes()),
            'profile' => is_array($providerUser->user) ? $providerUser->user : [],
            'last_used_at' => now(),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeScopes(mixed $scopes): array
    {
        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', trim($scopes)) ?: [];
        }

        if (! is_array($scopes)) {
            return [];
        }

        return collect($scopes)
            ->map(static fn (mixed $scope): string => trim((string) $scope))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = trim(mb_strtolower($email));

        return $email !== '' ? $email : null;
    }

    private function normalizeAvatarUrl(?string $avatarUrl): ?string
    {
        if (! is_string($avatarUrl) || $avatarUrl === '') {
            return null;
        }

        return filter_var($avatarUrl, FILTER_VALIDATE_URL) !== false ? $avatarUrl : null;
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    private function revokeAndError(ProviderAuth $providerAuth): array
    {
        $providerAuth->delete();

        Log::warning('Revoked local Spotify provider auth after playback/auth failure.', [
            'provider_auth_id' => $providerAuth->id,
            'provider_user_id' => $providerAuth->provider_user_id,
        ]);

        return $this->errorPayload('Spotify needs to be reconnected from the local widget launcher.');
    }

    private function driver()
    {
        $callbackUrl = route('local.widgets.spotify.callback');
        $configuredRedirect = (string) config('services.spotify.redirect');

        if ($configuredRedirect !== '' && rtrim($configuredRedirect, '/') !== rtrim($callbackUrl, '/')) {
            Log::warning('Local Spotify configured redirect does not match widget callback route; overriding at runtime.', [
                'configured_redirect' => $configuredRedirect,
                'callback_url' => $callbackUrl,
            ]);
        }

        return $this->socialite
            ->driver(ExternalAuthProvider::Spotify->value)
            ->redirectUrl($callbackUrl)
            ->setScopes($this->scopes());
    }
}
