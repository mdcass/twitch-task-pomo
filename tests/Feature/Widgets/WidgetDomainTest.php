<?php

namespace Tests\Feature\Widgets;

use App\Actions\CanvasWidgets\AttachWidgetToCanvas;
use App\Actions\CanvasWidgets\CreateRemoteCanvasWidget;
use App\Actions\Widgets\CreateWidget;
use App\Actions\Widgets\SetWidgetArchivedState;
use App\Actions\Widgets\UpdateFollowerGoalState;
use App\Actions\Widgets\UpdateWidget;
use App\Actions\Widgets\UpdateWidgetPublication;
use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetLifecycleState;
use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Enums\Models\WidgetType;
use App\Enums\TeamMemberRole;
use App\Models\Canvas;
use App\Models\ProviderAuth;
use App\Models\User;
use App\Models\Widget;
use App\Support\Widgets\WidgetDefinitionRegistry;
use App\Support\Widgets\WidgetLifecycleResolver;
use App\Support\Widgets\WidgetRenderDataFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class WidgetDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_policy_allows_team_members_to_view_and_only_owner_to_manage(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->taskList()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $this->assertTrue($owner->can('viewAny', Widget::class));
        $this->assertTrue($owner->can('view', $widget));
        $this->assertTrue($owner->can('create', Widget::class));
        $this->assertTrue($owner->can('update', $widget));
        $this->assertTrue($owner->can('delete', $widget));

        $this->assertTrue($member->can('viewAny', Widget::class));
        $this->assertTrue($member->can('view', $widget));
        $this->assertFalse($member->can('create', Widget::class));
        $this->assertFalse($member->can('update', $widget));
        $this->assertFalse($member->can('delete', $widget));
    }

    public function test_widget_definition_registry_and_render_data_factory_cover_all_supported_types(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $registry = app(WidgetDefinitionRegistry::class);
        $renderData = app(WidgetRenderDataFactory::class);

        $definitions = collect($registry->all())->keyBy(fn ($definition) => $definition->type()->value);

        $this->assertSame([
            WidgetType::TaskList->value,
            WidgetType::Pomodoro->value,
            WidgetType::FollowerGoal->value,
            WidgetType::SpotifyNowPlaying->value,
        ], $definitions->keys()->all());
        $this->assertNull($definitions[WidgetType::TaskList->value]->requiredProvider());
        $this->assertNull($definitions[WidgetType::Pomodoro->value]->requiredProvider());
        $this->assertSame(ExternalAuthProvider::Twitch, $definitions[WidgetType::FollowerGoal->value]->requiredProvider());
        $this->assertSame(ExternalAuthProvider::Spotify, $definitions[WidgetType::SpotifyNowPlaying->value]->requiredProvider());

        $taskList = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->create([
            'config' => [
                'title' => 'Focus Queue',
                'pending' => ['Plan sprint', 'Review notes'],
                'completed' => ['Warmup'],
            ],
        ]);
        $pomodoro = Widget::factory()->forTeam($user->currentTeam, $user)->pomodoro()->create([
            'config' => [
                'title' => 'Deep Work Sprint',
                'focus_minutes' => 45,
                'break_minutes' => 10,
                'state' => 'paused',
                'remaining_seconds' => 90,
            ],
        ]);
        $followerGoal = Widget::factory()->forTeam($user->currentTeam, $user)->followerGoal()->create([
            'config' => [
                'title' => 'Road to Partner',
                'goal_target' => 50,
                'end_date' => '2026-04-30',
                'sound_preset' => 'chime',
            ],
        ]);
        $followerGoal->followerGoalState()->create([
            'current_count' => 12,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);
        $spotify = Widget::factory()->forTeam($user->currentTeam, $user)->spotifyNowPlaying()->create();

        $this->assertSame('Focus Queue', $renderData->forWidget($taskList)['title']);
        $this->assertSame(['Plan sprint', 'Review notes'], $renderData->forWidget($taskList)['pendingItems']);

        $pomodoroData = $renderData->forWidget($pomodoro);
        $this->assertSame('Paused Timer', $pomodoroData['stateLabel']);
        $this->assertSame('01:30', $pomodoroData['countdownDisplay']);

        $followerGoalData = $renderData->forWidget($followerGoal);
        $this->assertSame(12, $followerGoalData['currentCount']);
        $this->assertSame(38, $followerGoalData['remainingCount']);
        $this->assertSame('2026-04-30', $followerGoalData['endDate']?->toDateString() ?? '2026-04-30');

        $spotifyData = $renderData->forWidget($spotify);
        $this->assertSame('Now Playing', $spotifyData['title']);
        $this->assertSame('error', $spotifyData['payload']['status']);
        $this->assertSame('Connect Spotify to enable this widget.', $spotifyData['payload']['message']);
    }

    public function test_create_widget_assigns_defaults_and_provider_backed_lifecycle_requirements(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $createWidget = app(CreateWidget::class);

        $taskList = $createWidget->create($user, $user->currentTeam, WidgetType::TaskList, '  Sprint Board  ');
        $followerGoal = $createWidget->create($user, $user->currentTeam, WidgetType::FollowerGoal);
        $spotify = $createWidget->create($user, $user->currentTeam, WidgetType::SpotifyNowPlaying);

        $this->assertSame('Sprint Board', $taskList->name);
        $this->assertSame($user->id, $taskList->created_by_user_id);
        $this->assertSame(WidgetLifecycleState::Ready, $taskList->lifecycle_state);
        $this->assertSame('Focus Queue', $taskList->config['title']);
        $this->assertSame('primary', $taskList->appearance['accent']);

        $this->assertSame(WidgetLifecycleState::PendingConnection, $followerGoal->lifecycle_state);
        $this->assertNotNull($followerGoal->followerGoalState);
        $this->assertSame(0, $followerGoal->followerGoalState->current_count);

        $this->assertSame(WidgetLifecycleState::PendingConnection, $spotify->lifecycle_state);
        $this->assertSame('Now Playing', $spotify->config['title']);
    }

    public function test_update_widget_normalizes_input_and_leaves_archived_widgets_archived(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $updateWidget = app(UpdateWidget::class);

        $taskList = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->ready()->create();
        $updatedTaskList = $updateWidget->update($user, $taskList, [
            'name' => '  Evening Queue  ',
            'config' => [
                'title' => 'Night Shift',
                'pending' => ['Plan stream', 'Review clips'],
                'completed' => ['Warmup'],
            ],
            'appearance' => [
                'accent' => 'success',
            ],
        ]);

        $this->assertSame('Evening Queue', $updatedTaskList->name);
        $this->assertSame('Night Shift', $updatedTaskList->config['title']);
        $this->assertSame(['Plan stream', 'Review clips'], $updatedTaskList->config['pending']);
        $this->assertSame(['Warmup'], $updatedTaskList->config['completed']);
        $this->assertSame('left', $updatedTaskList->appearance['title_alignment']);
        $this->assertSame('success', $updatedTaskList->appearance['accent']);
        $this->assertSame(WidgetLifecycleState::Ready, $updatedTaskList->lifecycle_state);

        $archived = Widget::factory()->forTeam($user->currentTeam, $user)->pomodoro()->create([
            'lifecycle_state' => WidgetLifecycleState::Archived,
        ]);

        $updatedArchived = $updateWidget->update($user, $archived, [
            'name' => '  Frozen Timer  ',
            'config' => [
                'title' => 'Paused Sprint',
                'focus_minutes' => 30,
                'break_minutes' => 10,
                'state' => 'paused',
            ],
            'appearance' => [
                'accent' => 'warning',
            ],
        ]);

        $this->assertSame('Frozen Timer', $updatedArchived->name);
        $this->assertSame('Paused Sprint', $updatedArchived->config['title']);
        $this->assertSame(WidgetLifecycleState::Archived, $updatedArchived->lifecycle_state);
    }

    public function test_update_widget_publication_manages_publication_and_runtime_state_independently(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $publication = app(UpdateWidgetPublication::class);

        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->create([
            'lifecycle_state' => WidgetLifecycleState::Draft,
            'published_at' => null,
            'publication_key' => null,
        ]);

        $published = $publication->publish($user, $widget);
        $this->assertTrue($published->isPublished());
        $this->assertSame(WidgetLifecycleState::Ready, $published->lifecycle_state);

        $originalKey = $published->publication_key;
        $rotated = $publication->rotate($user, $published);
        $this->assertNotSame($originalKey, $rotated->publication_key);
        $this->assertNotNull($rotated->published_at);

        $unpublished = $publication->unpublish($user, $rotated);
        $this->assertNull($unpublished->published_at);
        $this->assertSame($rotated->publication_key, $unpublished->publication_key);

        $archived = $publication->archive($user, $unpublished);
        $this->assertSame(WidgetLifecycleState::Archived, $archived->lifecycle_state);
        $this->assertNull($archived->published_at);

        $restored = $publication->restore($user, $archived);
        $this->assertSame(WidgetLifecycleState::Ready, $restored->lifecycle_state);
        $this->assertNull($restored->published_at);
    }

    public function test_set_widget_archived_state_preserves_publication_while_blocking_runtime_until_restore(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $archiveState = app(SetWidgetArchivedState::class);

        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->published()->create([
            'lifecycle_state' => WidgetLifecycleState::Ready,
        ]);

        $publishedAt = $widget->published_at;
        $publicationKey = $widget->publication_key;

        $archived = $archiveState->archive($user, $widget);
        $this->assertSame(WidgetLifecycleState::Archived, $archived->lifecycle_state);
        $this->assertSame($publishedAt?->toIso8601String(), $archived->published_at?->toIso8601String());
        $this->assertSame($publicationKey, $archived->publication_key);

        $restored = $archiveState->restore($user, $archived);
        $this->assertSame(WidgetLifecycleState::Ready, $restored->lifecycle_state);
        $this->assertSame($publishedAt?->toIso8601String(), $restored->published_at?->toIso8601String());
        $this->assertSame($publicationKey, $restored->publication_key);
    }

    public function test_reset_follower_goal_clears_progress_and_requires_a_goal_state(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $resetGoalState = app(UpdateFollowerGoalState::class);

        $followerGoal = Widget::factory()->forTeam($user->currentTeam, $user)->followerGoal()->create();
        $followerGoal->followerGoalState()->create([
            'current_count' => 17,
            'frozen_at' => now(),
            'last_followed_at' => now()->subMinute(),
        ]);

        $reset = $resetGoalState->reset($user, $followerGoal);

        $this->assertSame(0, $reset->followerGoalState->current_count);
        $this->assertNull($reset->followerGoalState->frozen_at);
        $this->assertNull($reset->followerGoalState->last_followed_at);

        $this->expectException(NotFoundHttpException::class);
        $resetGoalState->reset($user, Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->create());
    }

    public function test_attach_widget_and_remote_canvas_widget_creation_can_coexist_in_the_same_table(): void
    {
        Http::fake([
            'https://widgets.example.com/embed' => Http::response('', 200),
        ]);

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->pomodoro()->ready()->create();

        $attached = app(AttachWidgetToCanvas::class)->attach($user, $canvas, $widget);
        $remote = app(CreateRemoteCanvasWidget::class)->create($user, $canvas, [
            'name' => 'Countdown Widget',
            'embed_url' => 'https://widgets.example.com/embed',
        ]);

        $this->assertDatabaseCount('canvas_widgets', 2);
        $this->assertSame(WidgetSourceKind::Proprietary, $attached->source_kind);
        $this->assertSame($widget->id, $attached->widget_id);
        $this->assertSame(WidgetSourceKind::RemoteUrl, $remote->source_kind);
        $this->assertNull($remote->widget_id);
        $this->assertSame('Countdown Widget', $remote->name);
        $this->assertSame('https://widgets.example.com/embed', $remote->embed_url);
        $this->assertSame(WidgetPreviewStatus::Ready, $remote->preview_status);
    }

    public function test_attach_widget_requires_matching_team_and_remote_canvas_widget_creation_validates_https_urls(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $otherOwner = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();
        $foreignWidget = Widget::factory()->forTeam($otherOwner->currentTeam, $otherOwner)->taskList()->create();

        $this->expectException(NotFoundHttpException::class);
        app(AttachWidgetToCanvas::class)->attach($owner, $canvas, $foreignWidget);
    }

    public function test_remote_canvas_widget_action_rejects_invalid_urls_and_lifecycle_resolver_tracks_provider_connections(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $resolver = app(WidgetLifecycleResolver::class);

        try {
            app(CreateRemoteCanvasWidget::class)->create($user, $canvas, [
                'name' => 'Insecure Widget',
                'embed_url' => 'http://widgets.example.com/embed',
            ]);

            $this->fail('Expected remote placement validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'The embed URL must be a valid HTTPS URL.',
            ], $exception->errors()['embed_url']);
        }

        $taskList = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->create();
        $followerGoal = Widget::factory()->forTeam($user->currentTeam, $user)->followerGoal()->create();
        $archivedSpotify = Widget::factory()->forTeam($user->currentTeam, $user)->spotifyNowPlaying()->create([
            'lifecycle_state' => WidgetLifecycleState::Archived,
        ]);

        ProviderAuth::factory()->for($user)->create([
            'provider' => ExternalAuthProvider::Twitch,
        ]);

        $this->assertSame(WidgetLifecycleState::Ready, $resolver->resolve($taskList));
        $this->assertSame(WidgetLifecycleState::Ready, $resolver->resolve($followerGoal));
        $this->assertSame(WidgetLifecycleState::Archived, $resolver->resolve($archivedSpotify));
    }
}
