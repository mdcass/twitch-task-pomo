<?php

namespace Tests\Feature;

use App\Enums\ActivityEvent;
use App\Enums\ExternalAuthProvider;
use App\Enums\OauthFlow;
use App\Enums\TeamType;
use App\Enums\UserSettingKey;
use App\Models\Activity;
use App\Models\User;
use App\Models\WorkflowStore;
use App\Notifications\Auth\VerifyEmail;
use App\Workflows\Auth\SocialAuthHandshakeWorkflow;
use App\Workflows\Auth\SocialRegistrationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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
        $store = WorkflowStore::query()->sole();
        $workflow = $store->workflow();

        $this->assertSame(SocialAuthHandshakeWorkflow::class, $store->workflow_class);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('redirected'));
        $this->assertSame($store->id, session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));
        $this->assertSame('twitch', $workflow->getInitialContextValue('provider'));
        $this->assertSame(OauthFlow::Register->value, $workflow->getInitialContextValue('flow'));
        $this->assertNull($workflow->getInitialContextValue('legal_acceptance'));
        $this->assertNull($workflow->getInitialContextValue('debug_override'));
        $this->assertNull(session('oauth.pending'));
    }

    public function test_register_social_redirect_persists_debug_override_in_local_environment(): void
    {
        $this->useEnvironment('local');

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://provider.example/twitch/auth'));

        $response = $this->get('/oauth/twitch/redirect?flow=register&debug=no_email&terms=1');

        $response->assertRedirect('https://provider.example/twitch/auth');
        $store = WorkflowStore::query()->sole();
        $workflow = $store->workflow();

        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertSame('no_email', $workflow->getInitialContextValue('debug_override'));
        $this->assertIsArray($workflow->getInitialContextValue('legal_acceptance'));
    }

    public function test_register_social_redirect_rejects_invalid_debug_override_in_local_environment(): void
    {
        $this->useEnvironment('local');

        $response = $this->from('/register')->get('/oauth/twitch/redirect?flow=register&debug=not-an-email&terms=1');

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['debug']);
    }

    public function test_register_social_redirect_ignores_debug_override_outside_local_environment_even_when_debug_is_enabled(): void
    {
        config()->set('app.debug', true);
        $this->useEnvironment('production');

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://provider.example/twitch/auth'));

        $response = $this->get('/oauth/twitch/redirect?flow=register&debug=not-an-email&terms=1');

        $response->assertRedirect('https://provider.example/twitch/auth');
        $store = WorkflowStore::query()->sole();
        $workflow = $store->workflow();

        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertNull($workflow->getInitialContextValue('debug_override'));
    }

    public function test_login_social_redirect_does_not_require_legal_acceptance(): void
    {
        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://provider.example/twitch/auth'));

        $response = $this->get('/oauth/twitch/redirect?flow=login');

        $response->assertRedirect('https://provider.example/twitch/auth');
        $store = WorkflowStore::query()->sole();
        $workflow = $store->workflow();

        $this->assertSame(SocialAuthHandshakeWorkflow::class, $store->workflow_class);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('redirected'));
        $this->assertSame($store->id, session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));
        $this->assertSame('twitch', $workflow->getInitialContextValue('provider'));
        $this->assertSame(OauthFlow::Login->value, $workflow->getInitialContextValue('flow'));
    }

    public function test_callback_without_handshake_workflow_redirects_to_login_with_expired_error(): void
    {
        $response = $this->from('/login')->get('/oauth/callback/twitch?code=test-code&state=test-state');

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors([
            'social' => 'Your Twitch sign-in session expired. Please try again.',
        ]);

        $activity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialSessionInvalid->value)
            ->sole();

        $this->assertSame('oauth/callback/twitch', $activity->getExtraProperty('request_path'));
        $this->assertSame('handshake_missing', $activity->getExtraProperty('reason'));
        $this->assertNull($activity->team_id);
        $this->assertNull($activity->causer_id);
    }

    public function test_callback_with_provider_mismatch_redirects_to_login_with_expired_error(): void
    {
        $store = $this->createHandshakeWorkflowStore([
            'provider' => 'discord',
            'flow' => OauthFlow::Login->value,
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $store->id);

        $response = $this->from('/login')->get('/oauth/callback/twitch?code=test-code&state=test-state');

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors([
            'social' => 'Your Twitch sign-in session expired. Please try again.',
        ]);
        $this->assertNull(session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));

        $activity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialSessionInvalid->value)
            ->sole();

        $this->assertSame('oauth/callback/twitch', $activity->getExtraProperty('request_path'));
        $this->assertSame('provider_mismatch', $activity->getExtraProperty('reason'));
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
            'avatar_url' => 'https://cdn.example.test/avatars/old-twitch.png',
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'token_expires_at' => now()->subMinute(),
            'scopes' => ['old:scope'],
            'profile' => ['display_name' => 'Old'],
            'last_used_at' => now()->subDay(),
        ]);

        $store = $this->createHandshakeWorkflowStore([
            'provider' => 'twitch',
            'flow' => OauthFlow::Login->value,
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $store->id);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'twitch-user-123',
            'name' => 'Streamer Name',
            'nickname' => 'Streamer Name',
            'email' => 'streamer@example.test',
            'avatar' => 'https://cdn.example.test/avatars/new-twitch.png',
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
        $this->assertNull(session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));

        $providerAuth->refresh();
        $store->refresh();
        $workflow = $store->workflow();

        $this->assertSame('streamer@example.test', $providerAuth->provider_email);
        $this->assertSame('https://cdn.example.test/avatars/new-twitch.png', $providerAuth->avatar_url);
        $this->assertSame('new-access-token', $providerAuth->access_token);
        $this->assertSame('new-refresh-token', $providerAuth->refresh_token);
        $this->assertSame(['user:read:email'], $providerAuth->scopes);
        $this->assertSame('Streamer Name', $providerAuth->profile['display_name']);
        $this->assertNotNull($providerAuth->last_used_at);
        $this->assertTrue($providerAuth->token_expires_at?->isFuture());
        $this->assertSame('closed', $store->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('login_complete'));

        $loginActivity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialLoginSucceeded->value)
            ->sole();

        $providerActivity = Activity::query()
            ->where('event', ActivityEvent::ProviderAuthUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($providerActivity);
        $this->assertSame('oauth/callback/twitch', $loginActivity->getExtraProperty('request_path'));
        $this->assertSame('twitch', $loginActivity->getExtraProperty('provider'));
        $this->assertSame($user->current_team_id, $loginActivity->team_id);
        $this->assertSame('oauth/callback/twitch', $providerActivity->getExtraProperty('request_path'));
        $this->assertSame($user->current_team_id, $providerActivity->team_id);
        $this->assertArrayNotHasKey('access_token', $providerActivity->changes->get('attributes', []));
        $this->assertArrayNotHasKey('refresh_token', $providerActivity->changes->get('attributes', []));
        $this->assertArrayNotHasKey('profile', $providerActivity->changes->get('attributes', []));
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
            'avatar_url' => 'https://cdn.example.test/avatars/old-discord.png',
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'token_expires_at' => now()->subMinute(),
            'scopes' => ['identify'],
            'profile' => ['username' => 'Old'],
            'last_used_at' => now()->subDay(),
        ]);

        $store = $this->createHandshakeWorkflowStore([
            'provider' => 'discord',
            'flow' => OauthFlow::Login->value,
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $store->id);

        $driver = $this->mockDriver('discord');
        $driver->shouldReceive('setScopes')->once()->with(['identify', 'email'])->andReturnSelf();
        $driver->shouldReceive('withConsent')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'discord-user-123',
            'name' => 'Discord User',
            'nickname' => 'Discord User#1234',
            'email' => 'discord@example.test',
            'avatar' => 'https://cdn.example.test/avatars/new-discord.png',
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
        $this->assertNull(session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));

        $providerAuth->refresh();
        $store->refresh();
        $workflow = $store->workflow();

        $this->assertSame('discord@example.test', $providerAuth->provider_email);
        $this->assertSame('https://cdn.example.test/avatars/new-discord.png', $providerAuth->avatar_url);
        $this->assertSame('new-discord-token', $providerAuth->access_token);
        $this->assertSame('new-discord-refresh', $providerAuth->refresh_token);
        $this->assertSame(['identify', 'email'], $providerAuth->scopes);
        $this->assertSame('Discord User', $providerAuth->profile['username']);
        $this->assertTrue($providerAuth->token_expires_at?->isFuture());
        $this->assertSame('closed', $store->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('login_complete'));
    }

    public function test_login_callback_without_existing_provider_auth_redirects_to_register_and_closes_handshake(): void
    {
        $store = $this->createHandshakeWorkflowStore([
            'provider' => 'twitch',
            'flow' => OauthFlow::Login->value,
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $store->id);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'unlinked-twitch-user',
            'name' => 'Unlinked User',
            'nickname' => 'Unlinked User',
            'email' => 'unlinked@example.test',
            'token' => 'new-access-token',
            'refreshToken' => 'new-refresh-token',
            'expiresIn' => 3600,
            'approvedScopes' => ['user:read:email'],
            'raw' => [
                'id' => 'unlinked-twitch-user',
                'display_name' => 'Unlinked User',
                'email' => 'unlinked@example.test',
            ],
        ]));

        $response = $this->get('/oauth/callback/twitch?code=test-code&state=test-state');

        $response->assertRedirect(route('register', absolute: false));
        $response->assertSessionHasErrors([
            'social' => 'No Twitch account is linked here yet. Start from registration to create a new account.',
        ]);
        $this->assertGuest();
        $this->assertNull(session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));

        $store->refresh();
        $workflow = $store->workflow();

        $this->assertSame('closed', $store->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('callback_failed'));
        $this->assertSame('provider_auth_missing', $workflow->getContextValue('fail_callback', 'reason'));

        $activity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialLoginMissingLink->value)
            ->sole();

        $this->assertSame('oauth/callback/twitch', $activity->getExtraProperty('request_path'));
        $this->assertSame('provider_auth_missing', $activity->getExtraProperty('reason'));
        $this->assertNull($activity->team_id);
    }

    public function test_first_time_social_signup_creates_user_provider_auth_and_legal_acceptance(): void
    {
        Notification::fake();

        $store = $this->createHandshakeWorkflowStore([
            'provider' => 'twitch',
            'flow' => OauthFlow::Register->value,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => '2026-03-22T10:00:00+00:00',
                'privacy_policy_accepted_at' => '2026-03-22T10:00:00+00:00',
            ],
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $store->id);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'new-twitch-user',
            'name' => 'Fresh Streamer',
            'nickname' => 'Fresh Streamer',
            'email' => 'fresh@example.test',
            'avatar' => 'https://cdn.example.test/avatars/fresh-streamer.png',
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

        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertAuthenticated();
        $this->assertNull(session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));

        $user = User::query()->where('email', 'fresh@example.test')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $user->load('ownedTeams', 'currentTeam');
        $this->assertCount(1, $user->ownedTeams);
        $this->assertSame(TeamType::Streamer, $user->ownedTeams->first()->type);
        $this->assertTrue($user->currentTeam->is($user->ownedTeams->first()));
        $this->assertSame('Fresh\'s Streamer Profile', $user->currentTeam->name);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $providerAuth = $user->providerAuths()->sole();

        $this->assertSame(ExternalAuthProvider::Twitch, $providerAuth->provider);
        $this->assertSame('new-twitch-user', $providerAuth->provider_user_id);
        $this->assertSame('fresh@example.test', $providerAuth->provider_email);
        $this->assertSame('https://cdn.example.test/avatars/fresh-streamer.png', $providerAuth->avatar_url);
        $this->assertSame(['user:read:email'], $providerAuth->scopes);

        $legalAcceptance = $user->userSettings()
            ->where('key', UserSettingKey::LegalAcceptanceHistory)
            ->sole();

        $store->refresh();
        $workflow = $store->workflow();

        $this->assertSame('2026-03-22T10:00:00+00:00', $legalAcceptance->value['current']['terms_of_service_accepted_at']);
        $this->assertSame('2026-03-22T10:00:00+00:00', $legalAcceptance->value['current']['privacy_policy_accepted_at']);
        $this->assertCount(2, $legalAcceptance->value['history']);
        $this->assertSame('closed', $store->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('registration_complete'));
        $this->assertSame($user->email, $workflow->getContextValue('complete_registration', 'registered_email'));

        $registrationActivity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialRegistrationCompleted->value)
            ->sole();
        $providerActivity = Activity::query()
            ->where('event', ActivityEvent::ProviderAuthCreated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($providerActivity);
        $this->assertSame('oauth/callback/twitch', $registrationActivity->getExtraProperty('request_path'));
        $this->assertSame($user->current_team_id, $registrationActivity->team_id);
        $this->assertSame('2026-03-22T10:00:00+00:00', data_get($registrationActivity->properties->toArray(), 'legal_acceptance.terms_of_service_accepted_at'));
        $this->assertSame('2026-03-22T10:00:00+00:00', data_get($registrationActivity->properties->toArray(), 'legal_acceptance.privacy_policy_accepted_at'));
        $this->assertSame('registration', data_get($registrationActivity->properties->toArray(), 'legal_acceptance.source'));
        $this->assertSame('oauth/callback/twitch', $providerActivity->getExtraProperty('request_path'));
        $this->assertSame($user->current_team_id, $providerActivity->team_id);
        $this->assertArrayNotHasKey('access_token', $providerActivity->changes->get('attributes', []));
        $this->assertArrayNotHasKey('refresh_token', $providerActivity->changes->get('attributes', []));
        $this->assertArrayNotHasKey('profile', $providerActivity->changes->get('attributes', []));
    }

    public function test_matching_local_email_does_not_auto_link_or_authenticate(): void
    {
        User::factory()->create([
            'email' => 'existing@example.test',
        ]);

        $handshakeStore = $this->createHandshakeWorkflowStore([
            'provider' => 'discord',
            'flow' => OauthFlow::Register->value,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => now()->toIso8601String(),
                'privacy_policy_accepted_at' => now()->toIso8601String(),
            ],
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $handshakeStore->id);

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

        $response->assertRedirect(route('register.social-email', absolute: false));
        $this->assertGuest();
        $this->assertDatabaseCount('provider_auths', 0);
        $this->assertDatabaseCount('workflow_stores', 2);
        $this->assertNull(session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));
        $this->assertNotNull(session('workflow_store_id.'.SocialRegistrationWorkflow::class));

        $handshakeStore->refresh();
        $handshakeWorkflow = $handshakeStore->workflow();
        $registrationStore = WorkflowStore::query()
            ->where('workflow_class', SocialRegistrationWorkflow::class)
            ->sole();
        $workflow = $registrationStore->workflow();

        $this->assertSame('closed', $handshakeStore->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $handshakeWorkflow);
        $this->assertTrue($handshakeWorkflow->isState('registration_handoff'));
        $this->assertSame(SocialRegistrationWorkflow::class, $registrationStore->workflow_class);
        $this->assertInstanceOf(SocialRegistrationWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('existing_account_handoff'));
        $this->assertSame('existing@example.test', $workflow->getContextValue('show_existing_account_handoff', 'attempted_email'));

        $activity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialRegistrationBlockedExistingEmail->value)
            ->sole();

        $this->assertSame('oauth/callback/discord', $activity->getExtraProperty('request_path'));
        $this->assertSame('existing_local_email_match', $activity->getExtraProperty('reason'));
        $this->assertSame(SocialRegistrationWorkflow::class, data_get($activity->properties->toArray(), 'workflow.class'));
        $this->assertSame('existing_account_handoff', data_get($activity->properties->toArray(), 'workflow.state'));
        $this->assertNull($activity->team_id);
    }

    public function test_missing_provider_email_starts_social_registration_email_workflow(): void
    {
        $handshakeStore = $this->createHandshakeWorkflowStore([
            'provider' => 'twitch',
            'flow' => OauthFlow::Register->value,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => now()->toIso8601String(),
                'privacy_policy_accepted_at' => now()->toIso8601String(),
            ],
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $handshakeStore->id);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'new-twitch-user',
            'name' => 'No Email User',
            'nickname' => 'No Email User',
            'email' => null,
            'avatar' => 'https://cdn.example.test/avatars/no-email-user.png',
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

        $response->assertRedirect(route('register.social-email', absolute: false));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('provider_auths', 0);
        $this->assertDatabaseCount('user_settings', 0);
        $this->assertDatabaseCount('workflow_stores', 2);
        $this->assertNull(session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));
        $this->assertNotNull(session('workflow_store_id.'.SocialRegistrationWorkflow::class));

        $handshakeStore->refresh();
        $handshakeWorkflow = $handshakeStore->workflow();
        $registrationStore = WorkflowStore::query()
            ->where('workflow_class', SocialRegistrationWorkflow::class)
            ->sole();
        $workflow = $registrationStore->workflow();

        $this->assertSame('closed', $handshakeStore->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $handshakeWorkflow);
        $this->assertTrue($handshakeWorkflow->isState('registration_handoff'));
        $this->assertInstanceOf(SocialRegistrationWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('collect_email'));
        $this->assertSame('twitch', $workflow->getInitialContextValue('provider'));
        $this->assertNull($workflow->getInitialContextValue('effective_provider_email'));
        $this->assertSame('https://cdn.example.test/avatars/no-email-user.png', $workflow->getInitialContextValue('avatar_url'));
    }

    public function test_debug_no_email_override_forces_onboarding_even_when_provider_returns_email(): void
    {
        $this->useEnvironment('local');

        $handshakeStore = $this->createHandshakeWorkflowStore([
            'provider' => 'discord',
            'flow' => OauthFlow::Register->value,
            'debug_override' => 'no_email',
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => now()->toIso8601String(),
                'privacy_policy_accepted_at' => now()->toIso8601String(),
            ],
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $handshakeStore->id);

        $driver = $this->mockDriver('discord');
        $driver->shouldReceive('setScopes')->once()->with(['identify', 'email'])->andReturnSelf();
        $driver->shouldReceive('withConsent')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'discord-user-debug',
            'name' => 'Debug User',
            'nickname' => 'Debug User#0001',
            'email' => 'provider@example.test',
            'avatar' => 'https://cdn.example.test/avatars/debug-discord.png',
            'token' => 'debug-token',
            'refreshToken' => 'debug-refresh-token',
            'expiresIn' => 3600,
            'approvedScopes' => ['identify', 'email'],
            'raw' => [
                'id' => 'discord-user-debug',
                'username' => 'Debug User',
                'email' => 'provider@example.test',
            ],
        ]));

        $response = $this->get('/oauth/callback/discord?code=test-code&state=test-state');

        $response->assertRedirect(route('register.social-email', absolute: false));
        $this->assertGuest();

        $handshakeStore->refresh();
        $handshakeWorkflow = $handshakeStore->workflow();
        $registrationStore = WorkflowStore::query()
            ->where('workflow_class', SocialRegistrationWorkflow::class)
            ->sole();
        $workflow = $registrationStore->workflow();

        $this->assertSame('closed', $handshakeStore->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $handshakeWorkflow);
        $this->assertTrue($handshakeWorkflow->isState('registration_handoff'));
        $this->assertInstanceOf(SocialRegistrationWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('collect_email'));
        $this->assertSame('no_email', $workflow->getInitialContextValue('debug_override'));
        $this->assertNull($workflow->getInitialContextValue('effective_provider_email'));
        $this->assertSame('https://cdn.example.test/avatars/debug-discord.png', $workflow->getInitialContextValue('avatar_url'));
    }

    public function test_non_https_provider_avatar_is_not_persisted(): void
    {
        Notification::fake();

        $store = $this->createHandshakeWorkflowStore([
            'provider' => 'twitch',
            'flow' => OauthFlow::Register->value,
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => '2026-03-22T10:00:00+00:00',
                'privacy_policy_accepted_at' => '2026-03-22T10:00:00+00:00',
            ],
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $store->id);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'http-avatar-user',
            'name' => 'HTTP Avatar User',
            'nickname' => 'HTTP Avatar User',
            'email' => 'http-avatar@example.test',
            'avatar' => 'http://cdn.example.test/avatars/http-avatar-user.png',
            'token' => 'access-token-secret',
            'refreshToken' => 'refresh-token-secret',
            'expiresIn' => 3600,
            'approvedScopes' => ['user:read:email'],
            'raw' => [
                'id' => 'http-avatar-user',
                'display_name' => 'HTTP Avatar User',
                'email' => 'http-avatar@example.test',
            ],
        ]));

        $this->get('/oauth/callback/twitch?code=test-code&state=test-state')
            ->assertRedirect(route('verification.notice', absolute: false));

        $user = User::query()->where('email', 'http-avatar@example.test')->firstOrFail();
        $providerAuth = $user->providerAuths()->sole();

        $this->assertNull($providerAuth->avatar_url);
    }

    public function test_debug_email_override_can_force_existing_account_handoff(): void
    {
        $this->useEnvironment('local');

        User::factory()->create([
            'email' => 'existing@example.test',
        ]);

        $handshakeStore = $this->createHandshakeWorkflowStore([
            'provider' => 'twitch',
            'flow' => OauthFlow::Register->value,
            'debug_override' => 'existing@example.test',
            'legal_acceptance' => [
                'terms_of_service_accepted_at' => now()->toIso8601String(),
                'privacy_policy_accepted_at' => now()->toIso8601String(),
            ],
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $handshakeStore->id);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('setScopes')->once()->with(['user:read:email'])->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($this->socialiteUser([
            'id' => 'twitch-user-debug',
            'name' => 'Debug Twitch User',
            'nickname' => 'Debug Twitch User',
            'email' => 'provider@example.test',
            'token' => 'debug-token',
            'refreshToken' => 'debug-refresh-token',
            'expiresIn' => 3600,
            'approvedScopes' => ['user:read:email'],
            'raw' => [
                'id' => 'twitch-user-debug',
                'display_name' => 'Debug Twitch User',
                'email' => 'provider@example.test',
            ],
        ]));

        $response = $this->get('/oauth/callback/twitch?code=test-code&state=test-state');

        $response->assertRedirect(route('register.social-email', absolute: false));
        $this->assertGuest();

        $handshakeStore->refresh();
        $handshakeWorkflow = $handshakeStore->workflow();
        $registrationStore = WorkflowStore::query()
            ->where('workflow_class', SocialRegistrationWorkflow::class)
            ->sole();
        $workflow = $registrationStore->workflow();

        $this->assertSame('closed', $handshakeStore->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $handshakeWorkflow);
        $this->assertTrue($handshakeWorkflow->isState('registration_handoff'));
        $this->assertInstanceOf(SocialRegistrationWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('existing_account_handoff'));
        $this->assertSame('existing@example.test', $workflow->getContextValue('show_existing_account_handoff', 'attempted_email'));
    }

    public function test_socialite_callback_failure_closes_handshake_and_redirects_for_register_flow(): void
    {
        $store = $this->createHandshakeWorkflowStore([
            'provider' => 'discord',
            'flow' => OauthFlow::Register->value,
        ]);

        session()->put('workflow_store_id.'.SocialAuthHandshakeWorkflow::class, $store->id);

        $driver = $this->mockDriver('discord');
        $driver->shouldReceive('setScopes')->once()->with(['identify', 'email'])->andReturnSelf();
        $driver->shouldReceive('withConsent')->once()->andReturnSelf();
        $driver->shouldReceive('user')->once()->andThrow(new \RuntimeException('callback failed'));

        $response = $this->from('/register')->get('/oauth/callback/discord?code=test-code&state=test-state');

        $response->assertRedirect(route('register', absolute: false));
        $response->assertSessionHasErrors([
            'social' => 'We could not complete your Discord sign-in. Please try again.',
        ]);
        $this->assertNull(session('workflow_store_id.'.SocialAuthHandshakeWorkflow::class));

        $store->refresh();
        $workflow = $store->workflow();

        $this->assertSame('closed', $store->status->value);
        $this->assertInstanceOf(SocialAuthHandshakeWorkflow::class, $workflow);
        $this->assertTrue($workflow->isState('callback_failed'));

        $activity = Activity::query()
            ->where('event', ActivityEvent::AuthSocialCallbackFailed->value)
            ->sole();

        $this->assertSame('oauth/callback/discord', $activity->getExtraProperty('request_path'));
        $this->assertSame('provider_callback_exception', $activity->getExtraProperty('reason'));
        $this->assertSame('register', $activity->getExtraProperty('flow'));
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

    private function useEnvironment(string $environment): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createHandshakeWorkflowStore(array $context): WorkflowStore
    {
        return WorkflowStore::factory()->create([
            'team_id' => null,
            'created_by_user_id' => null,
            'workflow_class' => SocialAuthHandshakeWorkflow::class,
            'records' => [
                [
                    'from' => 'pending',
                    'to' => 'redirected',
                    'context' => $context,
                    'failed' => false,
                    'timestamp' => now()->toDateTimeString(),
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function socialiteUser(array $attributes): SocialiteUser
    {
        return tap(new SocialiteUser(), function (SocialiteUser $user) use ($attributes): void {
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
