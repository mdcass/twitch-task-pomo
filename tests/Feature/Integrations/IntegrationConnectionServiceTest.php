<?php

namespace Tests\Feature\Integrations;

use App\Actions\Integrations\IntegrationConnectionService;
use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetLifecycleState;
use App\Enums\TeamMemberRole;
use App\Models\ProviderAuth;
use App\Models\User;
use App\Models\Widget;
use App\Models\WorkflowStore;
use App\Workflows\Widgets\IntegrationConnectionWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IntegrationConnectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_starts_owner_workflow_for_widget_connections(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->spotifyNowPlaying()->create([
            'lifecycle_state' => WidgetLifecycleState::PendingConnection,
        ]);

        $this->actingAs($owner);

        $driver = $this->mockDriver('spotify');
        $driver->shouldReceive('redirectUrl')
            ->once()
            ->with(route('integrations.callback', ['provider' => 'spotify']))
            ->andReturnSelf();
        $driver->shouldReceive('setScopes')
            ->once()
            ->with(['user-read-email', 'user-read-currently-playing'])
            ->andReturnSelf();
        $driver->shouldReceive('with')
            ->once()
            ->with(['show_dialog' => 'true'])
            ->andReturnSelf();
        $driver->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse('https://provider.example.test/spotify/connect'));

        $response = app(IntegrationConnectionService::class)->redirect(
            $this->integrationRequest($owner, route('integrations.redirect', ['provider' => 'spotify'], false), [
                'widget' => $widget->id,
                'return_to' => route('widgets.show', $widget, false),
            ]),
            ExternalAuthProvider::Spotify,
            $widget,
        );

        $this->assertSame('https://provider.example.test/spotify/connect', $response->getTargetUrl());

        $store = WorkflowStore::query()->sole();
        $workflow = $store->workflow();

        $this->assertSame(IntegrationConnectionWorkflow::class, $store->workflow_class);
        $this->assertTrue($workflow->isState('redirected'));
        $this->assertSame('spotify', $workflow->getInitialContextValue('provider'));
        $this->assertSame(route('widgets.show', $widget, false), $workflow->getInitialContextValue('return_to'));
        $this->assertSame($widget->id, $workflow->getInitialContextValue('widget_id'));
        $this->assertSame($store->id, session('workflow_store_id.'.IntegrationConnectionWorkflow::class));
    }

    public function test_redirect_enforces_widget_authorization_and_owner_only_management(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $foreignOwner = User::factory()->withStreamerTeam()->create();
        $teamWidget = Widget::factory()->forTeam($owner->currentTeam, $owner)->spotifyNowPlaying()->create();
        $foreignWidget = Widget::factory()->forTeam($foreignOwner->currentTeam, $foreignOwner)->spotifyNowPlaying()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        try {
            $this->actingAs($owner);

            app(IntegrationConnectionService::class)->redirect(
                $this->integrationRequest($owner, route('integrations.redirect', ['provider' => 'spotify'], false), [
                    'widget' => $foreignWidget->id,
                ]),
                ExternalAuthProvider::Spotify,
                $foreignWidget,
            );

            $this->fail('Expected foreign widget authorization to fail.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('workflow_stores', 0);
        }

        try {
            $this->actingAs($member);

            app(IntegrationConnectionService::class)->redirect(
                $this->integrationRequest($member, route('integrations.redirect', ['provider' => 'spotify'], false), [
                    'widget' => $teamWidget->id,
                ]),
                ExternalAuthProvider::Spotify,
                $teamWidget,
            );

            $this->fail('Expected member redirect to be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_callback_success_persists_provider_auth_updates_twitch_stream_and_refreshes_widget_lifecycle(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $pendingWidget = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create([
            'lifecycle_state' => WidgetLifecycleState::PendingConnection,
        ]);
        $pendingWidget->followerGoalState()->create([
            'current_count' => 5,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);
        $archivedWidget = Widget::factory()->forTeam($owner->currentTeam, $owner)->spotifyNowPlaying()->create([
            'lifecycle_state' => WidgetLifecycleState::Archived,
        ]);

        $store = $this->createIntegrationWorkflowStore($owner, [
            'provider' => 'twitch',
            'return_to' => route('widgets.show', $pendingWidget, false),
            'widget_id' => $pendingWidget->id,
        ], $pendingWidget);
        session()->put('workflow_store_id.'.IntegrationConnectionWorkflow::class, $store->id);

        $this->actingAs($owner);

        $driver = $this->mockDriver('twitch');
        $driver->shouldReceive('redirectUrl')
            ->once()
            ->with(route('integrations.callback', ['provider' => 'twitch']))
            ->andReturnSelf();
        $driver->shouldReceive('setScopes')
            ->once()
            ->with(['user:read:email'])
            ->andReturnSelf();
        $driver->shouldReceive('user')
            ->once()
            ->andReturn($this->socialiteUser([
                'id' => 'twitch-user-123',
                'name' => 'Streamer Name',
                'nickname' => 'streamername',
                'email' => 'streamer@example.test',
                'avatar' => 'https://cdn.example.test/avatars/streamer.png',
                'token' => 'access-token',
                'refreshToken' => 'refresh-token',
                'expiresIn' => 3600,
                'approvedScopes' => ['user:read:email'],
                'raw' => [
                    'id' => 'twitch-user-123',
                    'login' => 'streamername',
                    'display_name' => 'Streamer Name',
                    'email' => 'streamer@example.test',
                ],
            ]));

        $response = app(IntegrationConnectionService::class)->callback(
            $this->integrationRequest($owner, route('integrations.callback', ['provider' => 'twitch'], false)),
            ExternalAuthProvider::Twitch,
        );

        $this->assertSame(route('widgets.show', $pendingWidget), $response->getTargetUrl());
        $this->assertSame('Twitch connected.', session('status'));

        $providerAuth = ProviderAuth::query()->sole();
        $this->assertSame($owner->id, $providerAuth->user_id);
        $this->assertSame(ExternalAuthProvider::Twitch, $providerAuth->provider);
        $this->assertSame('streamer@example.test', $providerAuth->provider_email);
        $this->assertSame(['user:read:email'], $providerAuth->scopes);

        $stream = $owner->currentTeam->streams()->sole();
        $this->assertSame($providerAuth->id, $stream->provider_auth_id);
        $this->assertSame('twitch-user-123', $stream->provider_channel_id);
        $this->assertSame('streamername', $stream->channel_login);

        $this->assertSame(WidgetLifecycleState::Ready, $pendingWidget->fresh()->lifecycle_state);
        $this->assertSame(WidgetLifecycleState::Archived, $archivedWidget->fresh()->lifecycle_state);

        $store->refresh();
        $this->assertSame('closed', $store->status->value);
        $this->assertTrue($store->workflow()->isState('completed'));
        $this->assertNull(session('workflow_store_id.'.IntegrationConnectionWorkflow::class));
    }

    public function test_callback_failure_closes_the_workflow_and_redirects_with_an_error(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $store = $this->createIntegrationWorkflowStore($owner, [
            'provider' => 'spotify',
            'return_to' => route('integrations.index', absolute: false),
            'widget_id' => null,
        ]);
        session()->put('workflow_store_id.'.IntegrationConnectionWorkflow::class, $store->id);

        $this->actingAs($owner);

        $driver = $this->mockDriver('spotify');
        $driver->shouldReceive('redirectUrl')
            ->once()
            ->with(route('integrations.callback', ['provider' => 'spotify']))
            ->andReturnSelf();
        $driver->shouldReceive('setScopes')
            ->once()
            ->with(['user-read-email', 'user-read-currently-playing'])
            ->andReturnSelf();
        $driver->shouldReceive('with')
            ->once()
            ->with(['show_dialog' => 'true'])
            ->andReturnSelf();
        $driver->shouldReceive('user')
            ->once()
            ->andThrow(new \RuntimeException('provider failed'));

        $response = app(IntegrationConnectionService::class)->callback(
            $this->integrationRequest($owner, route('integrations.callback', ['provider' => 'spotify'], false)),
            ExternalAuthProvider::Spotify,
        );

        $this->assertSame(route('integrations.index'), $response->getTargetUrl());
        $this->assertSame(
            'We could not complete the Spotify connection.',
            session('errors')->get('integration')[0] ?? null,
        );

        $this->assertDatabaseCount('provider_auths', 0);

        $store->refresh();
        $this->assertSame('closed', $store->status->value);
        $this->assertTrue($store->workflow()->isState('callback_failed'));
        $this->assertNull(session('workflow_store_id.'.IntegrationConnectionWorkflow::class));
    }

    public function test_disconnect_soft_deletes_owner_auth_and_refreshes_dependent_widget_lifecycle(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $providerAuth = ProviderAuth::factory()->for($owner)->spotify()->create();
        $readyWidget = Widget::factory()->forTeam($owner->currentTeam, $owner)->spotifyNowPlaying()->create([
            'lifecycle_state' => WidgetLifecycleState::Ready,
        ]);
        $archivedWidget = Widget::factory()->forTeam($owner->currentTeam, $owner)->spotifyNowPlaying()->create([
            'lifecycle_state' => WidgetLifecycleState::Archived,
        ]);

        app(IntegrationConnectionService::class)->disconnect($owner, ExternalAuthProvider::Spotify);

        $this->assertSoftDeleted('provider_auths', ['id' => $providerAuth->id]);
        $this->assertSame(WidgetLifecycleState::PendingConnection, $readyWidget->fresh()->lifecycle_state);
        $this->assertSame(WidgetLifecycleState::Archived, $archivedWidget->fresh()->lifecycle_state);

        try {
            app(IntegrationConnectionService::class)->disconnect($member, ExternalAuthProvider::Spotify);
            $this->fail('Expected member disconnect to be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function createIntegrationWorkflowStore(User $user, array $context, ?Widget $widget = null): WorkflowStore
    {
        return WorkflowStore::factory()->create([
            'team_id' => $user->current_team_id,
            'created_by_user_id' => $user->id,
            'subject_type' => $widget?->getMorphClass(),
            'subject_id' => $widget?->getKey(),
            'workflow_class' => IntegrationConnectionWorkflow::class,
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
     * @param  array<string, mixed>  $query
     */
    private function integrationRequest(User $user, string $uri, array $query = []): Request
    {
        session()->start();

        $request = Request::create($uri, 'GET', $query);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
