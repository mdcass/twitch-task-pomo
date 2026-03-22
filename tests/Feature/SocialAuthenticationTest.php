<?php

namespace Tests\Feature;

use App\Enums\ExternalAuthProvider;
use App\Enums\OauthFlow;
use App\Enums\UserSettingKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsupported_social_provider_routes_fail(): void
    {
        $this->get('/oauth/youtube/redirect?flow=login')->assertNotFound();
        $this->get('/oauth/callback/youtube')->assertNotFound();
    }

    public function test_register_social_redirect_requires_legal_acceptance(): void
    {
        $response = $this->from('/register')->get('/oauth/twitch/redirect?flow=register');

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['terms']);
    }

    public function test_oauth_redirect_rejects_invalid_flow_values(): void
    {
        $response = $this->from('/login')->get('/oauth/twitch/redirect?flow=invalid');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['flow']);
    }

    public function test_register_social_redirect_does_not_require_legal_acceptance_when_terms_feature_is_disabled(): void
    {
        $this->disableTermsFeature();

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://provider.example/twitch/auth'));

        $response = $this->get('/oauth/twitch/redirect?flow=register');

        $response->assertRedirect('https://provider.example/twitch/auth');
        $this->assertSame([
            'provider' => 'twitch',
            'flow' => OauthFlow::Register->value,
        ], session('oauth.pending'));
    }

    public function test_login_social_redirect_does_not_require_legal_acceptance(): void
    {
        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://provider.example/twitch/auth'));

        $response = $this->get('/oauth/twitch/redirect?flow=login');

        $response->assertRedirect('https://provider.example/twitch/auth');
        $this->assertSame([
            'provider' => 'twitch',
            'flow' => OauthFlow::Login->value,
        ], session('oauth.pending'));
    }

    public function test_register_screen_renders_both_legal_sections_when_terms_feature_is_enabled(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('name="terms"', false);
        $response->assertDontSee('name="accept_terms"', false);
        $response->assertDontSee('name="accept_privacy"', false);
        $response->assertSee('/oauth/twitch/redirect', false);
    }

    public function test_existing_twitch_provider_auth_logs_in_and_refreshes_provider_metadata(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now()->subDay(),
        ]);

        $providerAuth = $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Twitch,
            'provider_user_id' => 'twitch-user-123',
            'provider_email' => 'before@example.test',
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'token_expires_at' => now()->subMinute(),
            'scopes' => ['old:scope'],
            'profile' => ['display_name' => 'Old'],
            'last_used_at' => now()->subDay(),
        ]);

        session()->put('oauth.pending', [
            'provider' => 'twitch',
            'flow' => OauthFlow::Login->value,
        ]);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'twitch-user-123',
            'name' => 'Streamer Name',
            'nickname' => 'Streamer Name',
            'email' => 'streamer@example.test',
            'token' => 'new-access-token',
            'refreshToken' => 'new-refresh-token',
            'expiresIn' => 3600,
            'approvedScopes' => ['user:read:email'],
            'raw' => [
                'id' => 'twitch-user-123',
                'display_name' => 'Streamer Name',
                'email' => 'streamer@example.test',
            ],
        ]));

        $response = $this->get('/oauth/callback/twitch?code=test-code&state=test-state');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $providerAuth->refresh();

        $this->assertSame('streamer@example.test', $providerAuth->provider_email);
        $this->assertSame('new-access-token', $providerAuth->access_token);
        $this->assertSame('new-refresh-token', $providerAuth->refresh_token);
        $this->assertSame(['user:read:email'], $providerAuth->scopes);
        $this->assertSame('Streamer Name', $providerAuth->profile['display_name']);
        $this->assertNotNull($providerAuth->last_used_at);
        $this->assertTrue($providerAuth->token_expires_at?->isFuture());
    }

    public function test_existing_discord_provider_auth_logs_in_and_refreshes_provider_metadata(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now()->subDay(),
        ]);

        $providerAuth = $user->providerAuths()->create([
            'provider' => ExternalAuthProvider::Discord,
            'provider_user_id' => 'discord-user-123',
            'provider_email' => 'before@example.test',
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'token_expires_at' => now()->subMinute(),
            'scopes' => ['identify'],
            'profile' => ['username' => 'Old'],
            'last_used_at' => now()->subDay(),
        ]);

        session()->put('oauth.pending', [
            'provider' => 'discord',
            'flow' => OauthFlow::Login->value,
        ]);

        $driver = $this->mockDriver('discord');
        $driver->shouldReceive('setScopes')->once()->with(['identify', 'email'])->andReturnSelf();
        $driver->shouldReceive('withConsent')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'discord-user-123',
            'name' => 'Discord User',
            'nickname' => 'Discord User#1234',
            'email' => 'discord@example.test',
            'token' => 'new-discord-token',
            'refreshToken' => 'new-discord-refresh',
            'expiresIn' => 7200,
            'approvedScopes' => ['identify', 'email'],
            'raw' => [
                'id' => 'discord-user-123',
                'username' => 'Discord User',
                'email' => 'discord@example.test',
            ],
        ]));

        $response = $this->get('/oauth/callback/discord?code=test-code&state=test-state');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $providerAuth->refresh();

        $this->assertSame('discord@example.test', $providerAuth->provider_email);
        $this->assertSame('new-discord-token', $providerAuth->access_token);
        $this->assertSame('new-discord-refresh', $providerAuth->refresh_token);
        $this->assertSame(['identify', 'email'], $providerAuth->scopes);
        $this->assertSame('Discord User', $providerAuth->profile['username']);
        $this->assertTrue($providerAuth->token_expires_at?->isFuture());
    }

    public function test_first_time_social_signup_creates_user_provider_auth_and_legal_acceptance(): void
    {
        session()->put('oauth.pending', [
            'provider' => 'twitch',
            'flow' => OauthFlow::Register->value,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => '2026-03-22T10:00:00+00:00',
                'privacy_policy_accepted_at' => '2026-03-22T10:00:00+00:00',
            ],
        ]);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'new-twitch-user',
            'name' => 'Fresh Streamer',
            'nickname' => 'Fresh Streamer',
            'email' => 'fresh@example.test',
            'token' => 'access-token-secret',
            'refreshToken' => 'refresh-token-secret',
            'expiresIn' => 3600,
            'approvedScopes' => ['user:read:email'],
            'raw' => [
                'id' => 'new-twitch-user',
                'display_name' => 'Fresh Streamer',
                'login' => 'freshstreamer',
                'email' => 'fresh@example.test',
            ],
        ]));

        $response = $this->get('/oauth/callback/twitch?code=test-code&state=test-state');

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'fresh@example.test')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertCount(0, $user->ownedTeams);

        $providerAuth = $user->providerAuths()->sole();

        $this->assertSame(ExternalAuthProvider::Twitch, $providerAuth->provider);
        $this->assertSame('new-twitch-user', $providerAuth->provider_user_id);
        $this->assertSame('fresh@example.test', $providerAuth->provider_email);
        $this->assertSame(['user:read:email'], $providerAuth->scopes);

        $legalAcceptance = $user->userSettings()
            ->where('key', UserSettingKey::LegalAcceptanceHistory)
            ->sole();

        $this->assertSame('2026-03-22T10:00:00+00:00', $legalAcceptance->value['current']['terms_of_service_accepted_at']);
        $this->assertSame('2026-03-22T10:00:00+00:00', $legalAcceptance->value['current']['privacy_policy_accepted_at']);
        $this->assertCount(2, $legalAcceptance->value['history']);
    }

    public function test_matching_local_email_does_not_auto_link_or_authenticate(): void
    {
        User::factory()->create([
            'email' => 'existing@example.test',
        ]);

        session()->put('oauth.pending', [
            'provider' => 'discord',
            'flow' => OauthFlow::Register->value,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => now()->toIso8601String(),
                'privacy_policy_accepted_at' => now()->toIso8601String(),
            ],
        ]);

        $driver = $this->mockDriver('discord');
        $driver->shouldReceive('setScopes')->once()->with(['identify', 'email'])->andReturnSelf();
        $driver->shouldReceive('withConsent')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'discord-user-999',
            'name' => 'Existing Match',
            'nickname' => 'Existing Match#1234',
            'email' => 'existing@example.test',
            'token' => 'discord-access-token',
            'refreshToken' => 'discord-refresh-token',
            'expiresIn' => 3600,
            'approvedScopes' => ['identify', 'email'],
            'raw' => [
                'id' => 'discord-user-999',
                'username' => 'Existing Match',
                'email' => 'existing@example.test',
            ],
        ]));

        $response = $this->get('/oauth/callback/discord?code=test-code&state=test-state');

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertDatabaseCount('provider_auths', 0);
    }

    public function test_missing_provider_email_does_not_create_records_or_authenticate(): void
    {
        session()->put('oauth.pending', [
            'provider' => 'twitch',
            'flow' => OauthFlow::Register->value,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => now()->toIso8601String(),
                'privacy_policy_accepted_at' => now()->toIso8601String(),
            ],
        ]);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'new-twitch-user',
            'name' => 'No Email User',
            'nickname' => 'No Email User',
            'email' => null,
            'token' => 'access-token-secret',
            'refreshToken' => 'refresh-token-secret',
            'expiresIn' => 3600,
            'approvedScopes' => ['user:read:email'],
            'raw' => [
                'id' => 'new-twitch-user',
                'display_name' => 'No Email User',
            ],
        ]));

        $response = $this->get('/oauth/callback/twitch?code=test-code&state=test-state');

        $response->assertRedirect(route('register', absolute: false));
        $response->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('provider_auths', 0);
        $this->assertDatabaseCount('user_settings', 0);
    }

    public function test_base_test_case_prevents_unmocked_http_requests(): void
    {
        $this->expectException(StrayRequestException::class);

        Http::get('https://example.test');
    }

    private function mockDriver(string $provider): Mockery\MockInterface
    {
        $driver = Mockery::mock();

        Socialite::shouldReceive('driver')
            ->once()
            ->with($provider)
            ->andReturn($driver);

        return $driver;
    }

    private function enableTermsFeature(): void
    {
        config()->set('jetstream.features', [
            Features::termsAndPrivacyPolicy(),
            Features::accountDeletion(),
        ]);
    }

    private function disableTermsFeature(): void
    {
        config()->set('jetstream.features', [
            Features::accountDeletion(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function socialiteUser(array $attributes): SocialiteUser
    {
        return tap(new SocialiteUser, function (SocialiteUser $user) use ($attributes): void {
            $user->map([
                'id' => $attributes['id'],
                'nickname' => $attributes['nickname'] ?? null,
                'name' => $attributes['name'] ?? null,
                'email' => $attributes['email'] ?? null,
                'avatar' => $attributes['avatar'] ?? null,
            ]);
            $user->setRaw($attributes['raw'] ?? []);
            $user->token = $attributes['token'] ?? null;
            $user->refreshToken = $attributes['refreshToken'] ?? null;
            $user->expiresIn = $attributes['expiresIn'] ?? null;
            $user->approvedScopes = $attributes['approvedScopes'] ?? [];
        });
    }
}
