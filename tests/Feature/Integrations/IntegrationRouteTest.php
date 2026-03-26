<?php

namespace Tests\Feature\Integrations;

use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetLifecycleState;
use App\Enums\TeamMemberRole;
use App\Models\ProviderAuth;
use App\Models\User;
use App\Models\Widget;
use App\Models\WorkflowStore;
use App\Workflows\Widgets\IntegrationConnectionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class IntegrationRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrations_index_renders_owner_controls_and_member_read_only_state(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        ProviderAuth::factory()->for($owner)->create([
            'provider' => ExternalAuthProvider::Twitch,
            'provider_email' => 'owner@example.test',
        ]);
        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create([
            'name' => 'Partner Push',
            'lifecycle_state' => WidgetLifecycleState::Ready,
        ]);
        $widget->followerGoalState()->create([
            'current_count' => 8,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('integrations.index', absolute: false))
            ->assertOk()
            ->assertSee('Integrations')
            ->assertSee('Provider connections are user-owned in v1 and resolved through the current team owner for provider-backed widgets.')
            ->assertSee('Connected')
            ->assertSee('Disconnect Twitch')
            ->assertSee('Partner Push');

        $this->actingAs($member)
            ->get(route('integrations.index', absolute: false))
            ->assertOk()
            ->assertSee('Partner Push')
            ->assertDontSee('Connect Twitch')
            ->assertDontSee('Reconnect Twitch')
            ->assertDontSee('Disconnect Twitch');
    }

    public function test_integration_routes_smoke_connect_and_callback(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();

        $redirectDriver = $this->mockDriver('spotify');
        $redirectDriver->shouldReceive('redirectUrl')
            ->once()
            ->with(route('integrations.callback', ['provider' => 'spotify']))
            ->andReturnSelf();
        $redirectDriver->shouldReceive('setScopes')
            ->once()
            ->with(['user-read-email', 'user-read-currently-playing'])
            ->andReturnSelf();
        $redirectDriver->shouldReceive('with')
            ->once()
            ->with(['show_dialog' => 'true'])
            ->andReturnSelf();
        $redirectDriver->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://provider.example.test/spotify/connect'));

        $this->actingAs($owner)
            ->get(route('integrations.redirect', ['provider' => 'spotify'], false))
            ->assertRedirect('https://provider.example.test/spotify/connect');

        $this->assertNotNull(session('workflow_store_id.'.IntegrationConnectionWorkflow::class));

        $callbackStore = WorkflowStore::query()->sole();
        $callbackStore->update([
            'records' => [[
                'from' => 'pending',
                'to' => 'redirected',
                'context' => [
                    'provider' => 'spotify',
                    'return_to' => route('integrations.index', absolute: false),
                    'widget_id' => null,
                ],
                'failed' => false,
                'timestamp' => now()->toDateTimeString(),
            ]],
        ]);

        session()->put('workflow_store_id.'.IntegrationConnectionWorkflow::class, $callbackStore->id);

        $callbackDriver = $this->mockDriver('spotify');
        $callbackDriver->shouldReceive('redirectUrl')
            ->once()
            ->with(route('integrations.callback', ['provider' => 'spotify']))
            ->andReturnSelf();
        $callbackDriver->shouldReceive('setScopes')
            ->once()
            ->with(['user-read-email', 'user-read-currently-playing'])
            ->andReturnSelf();
        $callbackDriver->shouldReceive('with')
            ->once()
            ->with(['show_dialog' => 'true'])
            ->andReturnSelf();
        $callbackDriver->shouldReceive('user')
            ->once()
            ->andReturn($this->socialiteUser([
                'id' => 'spotify-user-123',
                'name' => 'Spotify Owner',
                'nickname' => 'spotifyowner',
                'email' => 'spotify-owner@example.test',
                'avatar' => 'https://cdn.example.test/avatars/spotify-owner.png',
                'token' => 'spotify-access-token',
                'refreshToken' => 'spotify-refresh-token',
                'expiresIn' => 3600,
                'approvedScopes' => ['user-read-email', 'user-read-currently-playing'],
                'raw' => [
                    'id' => 'spotify-user-123',
                    'display_name' => 'Spotify Owner',
                    'email' => 'spotify-owner@example.test',
                ],
            ]));

        $this->actingAs($owner)
            ->get(route('integrations.callback', ['provider' => 'spotify'], false))
            ->assertRedirect(route('integrations.index', absolute: false))
            ->assertSessionHas('status', 'Spotify connected.');

        $providerAuth = ProviderAuth::query()->where('provider', ExternalAuthProvider::Spotify)->sole();
        $this->assertSame($owner->id, $providerAuth->user_id);
    }

    public function test_integration_routes_reject_unsupported_providers(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();

        $this->actingAs($owner)
            ->get('/integrations/connect/youtube')
            ->assertNotFound();

        $this->actingAs($owner)
            ->get('/integrations/callback/youtube')
            ->assertNotFound();
    }

    public function test_integration_connect_route_scopes_widget_lookup_to_the_current_team(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $foreignOwner = User::factory()->withStreamerTeam()->create();
        $foreignWidget = Widget::factory()->forTeam($foreignOwner->currentTeam, $foreignOwner)->spotifyNowPlaying()->create();

        $this->actingAs($owner)
            ->get(route('integrations.redirect', [
                'provider' => 'spotify',
                'widget' => $foreignWidget->id,
            ], false))
            ->assertNotFound();
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
