<?php

namespace Tests\Feature;

use App\Actions\LocalWidgets\SpotifyWidgetService;
use App\Enums\ExternalAuthProvider;
use App\Livewire\LocalWidgets\SpotifyNowPlaying;
use App\Models\ProviderAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class LocalSpotifyWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.spotify.client_id', 'spotify-client');
        config()->set('services.spotify.client_secret', 'spotify-secret');
        config()->set('services.spotify.redirect', 'http://localhost/local/widgets/spotify/callback');
    }

    public function test_guest_oauth_routes_do_not_support_spotify(): void
    {
        $this->get('/oauth/spotify/redirect?flow=login')->assertNotFound();
        $this->get('/oauth/callback/spotify')->assertNotFound();
    }

    public function test_spotify_connect_redirects_to_the_provider(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $driver = $this->mockSpotifyDriver();

        $driver->shouldReceive('setScopes')
            ->once()
            ->with(['user-read-email', 'user-read-currently-playing'])
            ->andReturnSelf();
        $driver->shouldReceive('redirectUrl')
            ->once()
            ->with(route('local.widgets.spotify.callback'))
            ->andReturnSelf();
        $driver->shouldReceive('with')
            ->once()
            ->with(['show_dialog' => 'true'])
            ->andReturnSelf();
        $driver->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse('https://accounts.spotify.test/authorize'));

        $this->actingAs($user)
            ->get(route('local.widgets.spotify.connect', absolute: false))
            ->assertRedirect('https://accounts.spotify.test/authorize');
    }

    public function test_spotify_callback_persists_provider_auth_from_the_stored_connect_session(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $driver = $this->mockSpotifyDriver();

        $driver->shouldReceive('setScopes')
            ->once()
            ->with(['user-read-email', 'user-read-currently-playing'])
            ->andReturnSelf();
        $driver->shouldReceive('redirectUrl')
            ->once()
            ->with(route('local.widgets.spotify.callback'))
            ->andReturnSelf();
        $driver->shouldReceive('user')
            ->once()
            ->andReturn($this->socialiteUser([
                'id' => 'spotify-user-1',
                'name' => 'Desk Mix',
                'email' => 'desk-mix@example.test',
                'avatar' => 'https://cdn.example.test/avatars/desk-mix.png',
                'token' => 'spotify-access-token',
                'refreshToken' => 'spotify-refresh-token',
                'expiresIn' => 3600,
                'approvedScopes' => ['user-read-email', 'user-read-currently-playing'],
                'raw' => [
                    'id' => 'spotify-user-1',
                    'display_name' => 'Desk Mix',
                    'email' => 'desk-mix@example.test',
                    'images' => [
                        ['url' => 'https://cdn.example.test/avatars/desk-mix.png'],
                    ],
                ],
            ]));

        $this->withSession([
            'local_widgets.spotify_connect_user_id' => $user->id,
        ])
            ->get(route('local.widgets.spotify.callback', absolute: false))
            ->assertRedirect(route('local.widgets.index', absolute: false))
            ->assertSessionHas('status', 'Spotify connected for local widget previews.');

        $this->assertAuthenticatedAs($user);

        $providerAuth = ProviderAuth::query()
            ->where('provider', ExternalAuthProvider::Spotify)
            ->sole();

        $this->assertTrue($providerAuth->user->is($user));
        $this->assertSame('spotify-user-1', $providerAuth->provider_user_id);
        $this->assertSame('desk-mix@example.test', $providerAuth->provider_email);
        $this->assertSame('spotify-access-token', $providerAuth->access_token);
        $this->assertSame('spotify-refresh-token', $providerAuth->refresh_token);
        $this->assertSame(['user-read-email', 'user-read-currently-playing'], $providerAuth->scopes);
        $this->assertSame('Desk Mix', $providerAuth->profile['display_name']);
        $this->assertNotNull($providerAuth->last_used_at);
    }

    public function test_spotify_disconnect_soft_deletes_the_current_users_links(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $providerAuth = ProviderAuth::factory()->spotify()->for($user)->create();

        $this->actingAs($user)
            ->post(route('local.widgets.spotify.disconnect', absolute: false))
            ->assertRedirect(route('local.widgets.index', absolute: false))
            ->assertSessionHas('status', 'Spotify disconnected from local widget previews.');

        $this->assertSoftDeleted('provider_auths', [
            'id' => $providerAuth->id,
        ]);
    }

    public function test_public_spotify_widget_page_renders_without_the_authenticated_shell(): void
    {
        $this->get(route('local.widgets.spotify.show', absolute: false))
            ->assertOk()
            ->assertSee('Spotify Now Playing')
            ->assertSee('Reconnect Spotify')
            ->assertDontSee('data-shell-layout=', false);
    }

    public function test_spotify_now_playing_livewire_component_uses_the_most_recent_link_for_playing_state(): void
    {
        $olderUser = User::factory()->withStreamerTeam()->create();
        $latestUser = User::factory()->withStreamerTeam()->create();

        ProviderAuth::factory()->spotify()->for($olderUser)->create([
            'access_token' => 'older-access-token',
            'refresh_token' => 'older-refresh-token',
            'last_used_at' => now()->subMinutes(10),
        ]);

        ProviderAuth::factory()->spotify()->for($latestUser)->create([
            'access_token' => 'latest-access-token',
            'refresh_token' => 'latest-refresh-token',
            'provider_email' => 'latest@example.test',
            'profile' => ['display_name' => 'Latest Link'],
            'last_used_at' => now(),
        ]);

        Http::fake([
            'https://api.spotify.com/v1/me/player/currently-playing' => function (\Illuminate\Http\Client\Request $request) {
                $this->assertSame(['Bearer latest-access-token'], $request->header('Authorization'));

                return Http::response([
                    'is_playing' => true,
                    'item' => [
                        'name' => 'Calm Focus',
                        'artists' => [
                            ['name' => 'Deep Work FM'],
                        ],
                        'album' => [
                            'images' => [
                                ['url' => 'https://cdn.example.test/albums/focus.png'],
                            ],
                        ],
                    ],
                ]);
            },
        ]);

        Livewire::test(SpotifyNowPlaying::class)
            ->assertSet('status', 'playing')
            ->assertSet('track.title', 'Calm Focus')
            ->assertSet('track.artists_label', 'Deep Work FM')
            ->assertSet('source.display_name', 'Latest Link')
            ->assertSeeTextNormalized('Calm Focus')
            ->assertSeeTextNormalized('Deep Work FM');
    }

    public function test_spotify_now_playing_livewire_component_returns_idle_payload_when_spotify_is_not_playing(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        ProviderAuth::factory()->spotify()->for($user)->create([
            'access_token' => 'spotify-access-token',
            'refresh_token' => 'spotify-refresh-token',
            'last_used_at' => now(),
        ]);

        Http::fake([
            'https://api.spotify.com/v1/me/player/currently-playing' => Http::response('', 204),
        ]);

        Livewire::test(SpotifyNowPlaying::class)
            ->assertSet('status', 'idle')
            ->assertSet('message', 'Nothing playing right now.');
    }

    public function test_spotify_now_playing_livewire_component_refreshes_the_access_token_after_unauthorized_response(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $providerAuth = ProviderAuth::factory()->spotify()->for($user)->create([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'spotify-refresh-token',
            'last_used_at' => now(),
        ]);

        Http::fake([
            'https://api.spotify.com/v1/me/player/currently-playing' => Http::sequence()
                ->pushStatus(401)
                ->push([
                    'is_playing' => true,
                    'item' => [
                        'name' => 'Recovered Track',
                        'artists' => [
                            ['name' => 'Refresh Artist'],
                        ],
                        'album' => [
                            'images' => [
                                ['url' => 'https://cdn.example.test/albums/recovered.png'],
                            ],
                        ],
                    ],
                ]),
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 7200,
            ]),
        ]);

        Livewire::test(SpotifyNowPlaying::class)
            ->assertSet('status', 'playing')
            ->assertSet('track.title', 'Recovered Track');

        $providerAuth->refresh();

        $this->assertSame('new-access-token', $providerAuth->access_token);
        $this->assertSame('new-refresh-token', $providerAuth->refresh_token);
        $this->assertNotNull($providerAuth->token_expires_at);
        $this->assertNull($providerAuth->deleted_at);
    }

    public function test_spotify_now_playing_livewire_component_revokes_the_link_when_token_refresh_fails(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $providerAuth = ProviderAuth::factory()->spotify()->for($user)->create([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'spotify-refresh-token',
            'last_used_at' => now(),
        ]);

        Http::fake([
            'https://api.spotify.com/v1/me/player/currently-playing' => Http::response([], 401),
            'https://accounts.spotify.com/api/token' => Http::response([
                'error' => 'invalid_grant',
            ], 400),
        ]);

        Livewire::test(SpotifyNowPlaying::class)
            ->assertSet('status', 'error')
            ->assertSet('message', 'Spotify needs to be reconnected from the local widget launcher.')
            ->assertSeeTextNormalized('Spotify preview unavailable');

        $this->assertSoftDeleted('provider_auths', [
            'id' => $providerAuth->id,
        ]);
    }

    public function test_spotify_now_playing_livewire_component_renders_the_idle_state(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        ProviderAuth::factory()->spotify()->for($user)->create([
            'access_token' => 'spotify-access-token',
            'refresh_token' => 'spotify-refresh-token',
            'last_used_at' => now(),
        ]);

        Http::fake([
            'https://api.spotify.com/v1/me/player/currently-playing' => Http::response('', 204),
        ]);

        Livewire::test(SpotifyNowPlaying::class)
            ->assertSet('status', 'idle')
            ->assertSeeTextNormalized('Nothing playing')
            ->assertSeeTextNormalized('Start playback in Spotify');
    }

    private function mockSpotifyDriver(): Mockery\MockInterface
    {
        $driver = Mockery::mock();
        $factory = Mockery::mock(SocialiteFactory::class);

        $factory->shouldReceive('driver')
            ->once()
            ->with('spotify')
            ->andReturn($driver);

        $this->app->instance(SocialiteFactory::class, $factory);
        $this->app->forgetInstance(SpotifyWidgetService::class);

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
