<?php

namespace Tests\Feature\Widgets;

use App\Actions\WidgetInstances\AttachWidgetToCanvas;
use App\Actions\WidgetInstances\CreateRemoteWidget;
use App\Actions\Widgets\CreateWidget;
use App\Actions\Widgets\UpdateWidget;
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
use App\Models\WidgetInstance;
use App\Exceptions\DomainInvariantViolation;
use App\Support\Widgets\WidgetGeometry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
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

    public function test_widget_policy_returns_false_when_the_user_has_no_current_team(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('viewAny', Widget::class));
        $this->assertFalse($user->can('create', Widget::class));
    }

    public function test_widget_type_definitions_cover_all_supported_types_and_render_data(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $definitions = collect(WidgetType::cases())
            ->map(fn (WidgetType $type) => $type->definition())
            ->keyBy(fn ($definition) => $definition->type()->value);

        $this->assertSame([
            WidgetType::Pomodoro->value,
            WidgetType::TaskList->value,
            WidgetType::FollowerGoal->value,
            WidgetType::SpotifyNowPlaying->value,
        ], $definitions->keys()->all());
        $this->assertFalse($definitions[WidgetType::TaskList->value]->requiresProviderConnection());
        $this->assertFalse($definitions[WidgetType::Pomodoro->value]->requiresProviderConnection());
        $this->assertTrue($definitions[WidgetType::FollowerGoal->value]->requiresProviderConnection());
        $this->assertTrue($definitions[WidgetType::SpotifyNowPlaying->value]->requiresProviderConnection());
        $this->assertNull($definitions[WidgetType::TaskList->value]->requiredProvider());
        $this->assertNull($definitions[WidgetType::Pomodoro->value]->requiredProvider());
        $this->assertSame(ExternalAuthProvider::Twitch, $definitions[WidgetType::FollowerGoal->value]->requiredProvider());
        $this->assertSame(ExternalAuthProvider::Spotify, $definitions[WidgetType::SpotifyNowPlaying->value]->requiredProvider());

        $twitchTypes = collect(WidgetType::cases())
            ->filter(fn (WidgetType $type): bool => $type->definition()->requiredProvider() === ExternalAuthProvider::Twitch)
            ->values();
        $spotifyTypes = collect(WidgetType::cases())
            ->filter(fn (WidgetType $type): bool => $type->definition()->requiredProvider() === ExternalAuthProvider::Spotify)
            ->values();

        $this->assertSame([WidgetType::FollowerGoal], $twitchTypes->all());
        $this->assertSame(
            [WidgetType::FollowerGoal->value],
            $twitchTypes->map(fn (WidgetType $type): string => $type->value)->all(),
        );

        $this->assertSame([WidgetType::SpotifyNowPlaying], $spotifyTypes->all());
        $this->assertSame(
            [WidgetType::SpotifyNowPlaying->value],
            $spotifyTypes->map(fn (WidgetType $type): string => $type->value)->all(),
        );

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
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $taskListInstance = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'widget_id' => $taskList->id,
            'source_kind' => WidgetSourceKind::Proprietary,
        ]);

        $taskListData = $taskList->definition()->renderData($taskList);
        $this->assertEquals($definitions[WidgetType::TaskList->value]->renderData($taskList), $taskListData);
        $this->assertSame('Focus Queue', $taskListData['title']);
        $this->assertSame(['Plan sprint', 'Review notes'], $taskListData['pendingItems']);
        $this->assertEquals(
            $definitions[WidgetType::TaskList->value]->renderData($taskList),
            $taskListInstance->fresh('widget')->widget->definition()->renderData($taskList),
        );

        $pomodoroData = $pomodoro->definition()->renderData($pomodoro);
        $this->assertEquals($definitions[WidgetType::Pomodoro->value]->renderData($pomodoro), $pomodoroData);
        $this->assertSame('Paused Timer', $pomodoroData['stateLabel']);
        $this->assertSame('01:30', $pomodoroData['countdownDisplay']);

        $followerGoalData = $followerGoal->definition()->renderData($followerGoal);
        $this->assertEquals($definitions[WidgetType::FollowerGoal->value]->renderData($followerGoal), $followerGoalData);
        $this->assertSame(12, $followerGoalData['currentCount']);
        $this->assertSame(38, $followerGoalData['remainingCount']);
        $this->assertSame('2026-04-30', $followerGoalData['endDate']?->toDateString() ?? '2026-04-30');

        $spotifyData = $spotify->definition()->renderData($spotify);
        $this->assertEquals($definitions[WidgetType::SpotifyNowPlaying->value]->renderData($spotify), $spotifyData);
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
        $this->assertSame('Ready', $taskList->healthLabel());
        $this->assertSame('Off', $taskList->standaloneStatusLabel());
        $this->assertSame('Focus Queue', $taskList->config['title']);
        $this->assertSame('primary', $taskList->appearance['accent']);

        $this->assertSame(WidgetLifecycleState::PendingConnection, $followerGoal->lifecycle_state);
        $this->assertSame('Needs Setup', $followerGoal->healthLabel());
        $this->assertNotNull($followerGoal->followerGoalState);
        $this->assertSame(0, $followerGoal->followerGoalState->current_count);
        $this->assertDatabaseCount('widget_follower_goal_states', 1);
        $this->assertDatabaseHas('widget_follower_goal_states', [
            'widget_id' => $followerGoal->id,
            'current_count' => 0,
        ]);

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

        $archived = Widget::factory()->forTeam($user->currentTeam, $user)->pomodoro()->archived()->create();

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

    public function test_update_widget_preserves_follower_goal_progress_when_config_changes(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $updateWidget = app(UpdateWidget::class);

        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->followerGoal()->create([
            'name' => 'Partner Push',
        ]);
        $widget->followerGoalState()->create([
            'current_count' => 17,
            'frozen_at' => now(),
            'last_followed_at' => now()->subMinute(),
        ]);

        $updated = $updateWidget->update($user, $widget, [
            'name' => 'Updated Partner Push',
            'config' => [
                'title' => 'Road to Partner',
                'goal_target' => 100,
                'end_date' => '2026-04-30',
                'sound_preset' => 'none',
            ],
            'appearance' => [
                'accent' => 'warning',
            ],
        ]);

        $updated->load('followerGoalState');

        $this->assertSame('Updated Partner Push', $updated->name);
        $this->assertSame(100, $updated->config['goal_target']);
        $this->assertSame(17, $updated->followerGoalState->current_count);
        $this->assertNotNull($updated->followerGoalState->frozen_at);
        $this->assertNotNull($updated->followerGoalState->last_followed_at);
        $this->assertDatabaseHas('widget_follower_goal_states', [
            'widget_id' => $widget->id,
            'current_count' => 17,
        ]);
    }

    public function test_widget_publication_methods_manage_publication_and_runtime_state_independently(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->create([
            'lifecycle_state' => WidgetLifecycleState::Draft,
            'published_at' => null,
            'publication_key' => null,
        ]);

        $published = tap($widget)->publish();
        $this->assertTrue($published->isPublished());
        $this->assertSame(WidgetLifecycleState::Ready, $published->lifecycle_state);
        $this->assertSame('Live', $published->standaloneStatusLabel());

        $originalKey = $published->publication_key;
        $rotated = $published->regeneratePublicationKey();
        $this->assertNotSame($originalKey, $rotated->publication_key);
        $this->assertNotNull($rotated->published_at);

        $unpublished = $rotated->unpublish();
        $this->assertNull($unpublished->published_at);
        $this->assertSame($rotated->publication_key, $unpublished->publication_key);

        $republished = $unpublished->publish();
        $this->assertNotNull($republished->published_at);
        $this->assertSame($rotated->publication_key, $republished->publication_key);

        $archived = $unpublished->archive();
        $this->assertSame(WidgetLifecycleState::Archived, $archived->lifecycle_state);
        $this->assertNull($archived->published_at);
        $this->assertSame($rotated->publication_key, $archived->publication_key);

        $restored = $archived->restore();
        $this->assertSame(WidgetLifecycleState::Ready, $restored->lifecycle_state);
        $this->assertNull($restored->published_at);
        $this->assertSame($rotated->publication_key, $restored->publication_key);
    }

    public function test_widget_publish_rejects_archived_and_broken_widgets(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $archived = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->archived()->create();
        $broken = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->broken()->create();

        try {
            $archived->publish();

            $this->fail('Expected archived widgets to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'Archived widgets cannot be published.',
            ], $exception->errors()['lifecycle_state']);
        }

        try {
            $broken->publish();

            $this->fail('Expected broken widgets to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'Broken widgets cannot be published.',
            ], $exception->errors()['lifecycle_state']);
        }
    }

    public function test_widget_archive_clears_publication_while_blocking_runtime_until_restore(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->published()->create([
            'lifecycle_state' => WidgetLifecycleState::Ready,
        ]);

        $publicationKey = $widget->publication_key;

        $archived = $widget->archive();
        $this->assertSame(WidgetLifecycleState::Archived, $archived->lifecycle_state);
        $this->assertNull($archived->published_at);
        $this->assertSame($publicationKey, $archived->publication_key);

        $restored = $archived->restore();
        $this->assertSame(WidgetLifecycleState::Ready, $restored->lifecycle_state);
        $this->assertNull($restored->published_at);
        $this->assertSame($publicationKey, $restored->publication_key);
    }

    public function test_widget_reset_follower_goal_clears_progress_and_requires_a_goal_state(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $followerGoal = Widget::factory()->forTeam($user->currentTeam, $user)->followerGoal()->create();
        $followerGoal->followerGoalState()->create([
            'current_count' => 17,
            'frozen_at' => now(),
            'last_followed_at' => now()->subMinute(),
        ]);

        $reset = $followerGoal->resetFollowerGoal();

        $this->assertSame(0, $reset->followerGoalState->current_count);
        $this->assertNull($reset->followerGoalState->frozen_at);
        $this->assertNull($reset->followerGoalState->last_followed_at);
        $this->assertDatabaseHas('widget_follower_goal_states', [
            'widget_id' => $followerGoal->id,
            'current_count' => 0,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        $this->expectException(DomainInvariantViolation::class);
        Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->create()->resetFollowerGoal();
    }

    public function test_attach_and_remote_widget_creation_share_common_widget_instance_defaults(): void
    {
        Http::fake([
            'https://widgets.example.com/embed' => Http::response('', 200),
        ]);

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $remoteCanvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->pomodoro()->ready()->create();

        $attached = app(AttachWidgetToCanvas::class)->attach($user, $canvas, $widget);
        $secondWidget = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->ready()->create();
        $secondAttached = app(AttachWidgetToCanvas::class)->attach($user, $canvas, $secondWidget);
        $remote = app(CreateRemoteWidget::class)->create($user, $remoteCanvas, [
            'name' => 'Countdown Widget',
            'embed_url' => 'https://widgets.example.com/embed',
        ]);

        $this->assertDatabaseCount('canvas_widgets', 3);
        $this->assertSame(WidgetSourceKind::Proprietary, $attached->source_kind);
        $this->assertSame($widget->id, $attached->widget_id);
        $this->assertSame(WidgetSourceKind::RemoteUrl, $remote->source_kind);
        $this->assertNull($remote->widget_id);
        $this->assertSame('Countdown Widget', $remote->name);
        $this->assertSame('https://widgets.example.com/embed', $remote->embed_url);
        $this->assertSame(WidgetPreviewStatus::Ready, $remote->preview_status);
        $this->assertSame(0, $attached->crop_top);
        $this->assertSame(0, $attached->crop_right);
        $this->assertSame(0, $attached->crop_bottom);
        $this->assertSame(0, $attached->crop_left);
        $this->assertSame(WidgetGeometry::DEFAULT_ATTACHED_WIDGET_BASE_X, $attached->position_x);
        $this->assertSame(WidgetGeometry::DEFAULT_ATTACHED_WIDGET_BASE_Y, $attached->position_y);
        $this->assertSame(
            WidgetGeometry::DEFAULT_ATTACHED_WIDGET_BASE_X + WidgetGeometry::DEFAULT_ATTACHED_WIDGET_STEP_X,
            $secondAttached->position_x,
        );
        $this->assertSame(
            WidgetGeometry::DEFAULT_ATTACHED_WIDGET_BASE_Y + WidgetGeometry::DEFAULT_ATTACHED_WIDGET_STEP_Y,
            $secondAttached->position_y,
        );
        $this->assertSame(0, $remote->crop_top);
        $this->assertSame(0, $remote->crop_right);
        $this->assertSame(0, $remote->crop_bottom);
        $this->assertSame(0, $remote->crop_left);
        $this->assertSame(WidgetGeometry::DEFAULT_REMOTE_WIDGET_BASE_X, $remote->position_x);
        $this->assertSame(WidgetGeometry::DEFAULT_REMOTE_WIDGET_BASE_Y, $remote->position_y);
        $this->assertSame(WidgetGeometry::DEFAULT_REMOTE_WIDGET_WIDTH, $remote->width);
        $this->assertSame(WidgetGeometry::DEFAULT_REMOTE_WIDGET_HEIGHT, $remote->height);
        $this->assertSame([
            'frame_width' => $attached->width,
            'frame_height' => $attached->height,
            'content_width' => $attached->content_width,
            'content_height' => $attached->content_height,
        ], $attached->settings['editor_defaults'] ?? null);
        $this->assertSame([
            'frame_width' => $remote->width,
            'frame_height' => $remote->height,
            'content_width' => $remote->content_width,
            'content_height' => $remote->content_height,
        ], $remote->settings['editor_defaults'] ?? null);
        $this->assertSame(1, $attached->z_index);
        $this->assertSame(2, $secondAttached->z_index);
        $this->assertSame(1, $remote->z_index);
    }

    public function test_attach_widget_requires_matching_team(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $otherOwner = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();
        $foreignWidget = Widget::factory()->forTeam($otherOwner->currentTeam, $otherOwner)->taskList()->create();

        $this->expectException(AuthorizationException::class);
        app(AttachWidgetToCanvas::class)->attach($owner, $canvas, $foreignWidget);
    }

    public function test_remote_widget_action_rejects_invalid_urls_and_widget_lifecycle_tracks_provider_connections(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();

        try {
            app(CreateRemoteWidget::class)->create($user, $canvas, [
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
        $archivedSpotify = Widget::factory()->forTeam($user->currentTeam, $user)->spotifyNowPlaying()->archived()->create();

        ProviderAuth::factory()->for($user)->create([
            'provider' => ExternalAuthProvider::Twitch,
            'access_token' => 'twitch-access-token',
        ]);

        $this->assertSame(WidgetLifecycleState::Ready, $taskList->refreshLifecycle()->lifecycle_state);
        $this->assertSame(WidgetLifecycleState::Ready, $followerGoal->refreshLifecycle()->lifecycle_state);
        $this->assertSame(WidgetLifecycleState::Archived, $archivedSpotify->refreshLifecycle()->lifecycle_state);
    }

    public function test_widget_instance_model_invariants_reject_invalid_source_and_team_state(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->taskList()->create();

        try {
            WidgetInstance::query()->create([
                'canvas_id' => $canvas->id,
                'widget_id' => null,
                'team_id' => $canvas->team_id,
                'source_kind' => WidgetSourceKind::Proprietary,
                'position_x' => 0,
                'position_y' => 0,
                'width' => 320,
                'height' => 180,
                'content_width' => 320,
                'content_height' => 180,
                'crop_top' => 0,
                'crop_right' => 0,
                'crop_bottom' => 0,
                'crop_left' => 0,
                'z_index' => 0,
                'is_visible' => true,
                'preview_status' => WidgetPreviewStatus::Ready,
            ]);
            $this->fail('Expected a proprietary widget instance without a backing widget to be rejected.');
        } catch (DomainInvariantViolation $exception) {
            $this->assertSame('Proprietary widget instances must reference a backing widget.', $exception->getMessage());
        }

        try {
            WidgetInstance::query()->create([
                'canvas_id' => $canvas->id,
                'widget_id' => $widget->id,
                'team_id' => $canvas->team_id,
                'source_kind' => WidgetSourceKind::RemoteUrl,
                'embed_url' => 'https://widgets.example.test/embed',
                'position_x' => 0,
                'position_y' => 0,
                'width' => 320,
                'height' => 180,
                'content_width' => 320,
                'content_height' => 180,
                'crop_top' => 0,
                'crop_right' => 0,
                'crop_bottom' => 0,
                'crop_left' => 0,
                'z_index' => 0,
                'is_visible' => true,
                'preview_status' => WidgetPreviewStatus::Ready,
            ]);
            $this->fail('Expected a remote widget instance with a proprietary backing widget to be rejected.');
        } catch (DomainInvariantViolation $exception) {
            $this->assertSame('Remote widget instances may not reference a proprietary widget.', $exception->getMessage());
        }
    }

    public function test_proprietary_widget_instance_display_paths_fail_loudly_when_the_backing_widget_is_missing(): void
    {
        $widget = WidgetInstance::factory()->withoutBackingWidget()->make([
            'width' => 320,
            'height' => 180,
            'content_width' => 320,
            'content_height' => 180,
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
        ]);

        $this->expectException(DomainInvariantViolation::class);

        $widget->displayName();
    }
}
