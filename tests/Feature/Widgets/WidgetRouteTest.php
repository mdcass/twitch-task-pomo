<?php

namespace Tests\Feature\Widgets;

use App\Enums\Models\WidgetLifecycleState;
use App\Enums\TeamMemberRole;
use App\Models\Canvas;
use App\Models\User;
use App\Models\Widget;
use App\Models\WidgetInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WidgetRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_widgets_index_renders_filters_and_excludes_remote_canvas_embeds_from_the_library(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();

        $matchingWidget = Widget::factory()->forTeam($owner->currentTeam, $owner)->taskList()->ready()->create([
            'name' => 'Focus Queue',
        ]);
        Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create([
            'name' => 'Audience Milestone',
        ]);
        WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
            'team_id' => $owner->currentTeam->id,
            'name' => 'Remote Countdown',
        ]);

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $this->actingAs($member)
            ->get(route('widgets.index', [
                'search' => 'Focus',
                'type' => $matchingWidget->type->value,
                'lifecycle' => WidgetLifecycleState::Ready->value,
            ], false))
            ->assertOk()
            ->assertSee('Manage reusable proprietary widgets for canvas placement and optional standalone browser-source URLs.')
            ->assertSee('Focus Queue')
            ->assertDontSee('Audience Milestone')
            ->assertDontSee('Remote Countdown');
    }

    public function test_widget_edit_route_allows_team_members_blocks_outsiders_and_replaces_show(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->withStreamerTeam()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create([
            'name' => 'Partner Push',
        ]);
        $widget->followerGoalState()->create([
            'current_count' => 4,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        $this->actingAs($member)
            ->get(route('widgets.edit', $widget, false))
            ->assertOk()
            ->assertSee('Partner Push')
            ->assertSee('Owner action required: connect Twitch')
            ->assertSee('Reusable proprietary widget for canvas placement and optional standalone browser-source output. Remote embeds stay canvas-scoped and are managed from canvas pages only.');

        $this->actingAs($outsider)
            ->get(route('widgets.edit', $widget, false))
            ->assertForbidden();

        $this->actingAs($member)
            ->get('/widgets/'.$widget->id)
            ->assertNotFound();
    }

    public function test_published_widget_overlay_route_requires_a_matching_key_and_a_ready_widget(): void
    {
        $widget = Widget::factory()->taskList()->published()->create([
            'lifecycle_state' => WidgetLifecycleState::Ready,
            'config' => [
                'title' => 'Published Queue',
                'pending' => ['Plan stream'],
                'completed' => [],
            ],
        ]);

        $signedUrl = URL::signedRoute('overlay.widgets.published', [
            'widget' => $widget->uuid,
            'key' => $widget->publication_key,
        ], absolute: false);

        $this->get($signedUrl)
            ->assertOk()
            ->assertSee('Published Queue')
            ->assertDontSee('data-shell-layout=', false);

        $wrongKeyUrl = URL::signedRoute('overlay.widgets.published', [
            'widget' => $widget->uuid,
            'key' => 'wrong-key',
        ], absolute: false);

        $this->get($wrongKeyUrl)->assertNotFound();

        $widget->forceFill(['published_at' => null])->save();

        $unpublishedUrl = URL::signedRoute('overlay.widgets.published', [
            'widget' => $widget->uuid,
            'key' => $widget->publication_key,
        ], absolute: false);

        $this->get($unpublishedUrl)->assertNotFound();

        $widget->forceFill([
            'published_at' => now(),
            'lifecycle_state' => WidgetLifecycleState::PendingConnection,
        ])->save();

        $pendingUrl = URL::signedRoute('overlay.widgets.published', [
            'widget' => $widget->uuid,
            'key' => $widget->publication_key,
        ], absolute: false);

        $this->get($pendingUrl)->assertNotFound();

        $widget->forceFill([
            'published_at' => now(),
            'lifecycle_state' => WidgetLifecycleState::Broken,
        ])->save();

        $brokenUrl = URL::signedRoute('overlay.widgets.published', [
            'widget' => $widget->uuid,
            'key' => $widget->publication_key,
        ], absolute: false);

        $this->get($brokenUrl)->assertNotFound();

        $widget->forceFill([
            'published_at' => now(),
            'lifecycle_state' => WidgetLifecycleState::Archived,
        ])->save();

        $archivedUrl = URL::signedRoute('overlay.widgets.published', [
            'widget' => $widget->uuid,
            'key' => $widget->publication_key,
        ], absolute: false);

        $this->get($archivedUrl)->assertNotFound();
    }

    public function test_published_follower_goal_widget_renders_with_zero_progress_from_its_child_state(): void
    {
        $widget = Widget::factory()->followerGoal()->published()->create([
            'lifecycle_state' => WidgetLifecycleState::Ready,
            'config' => [
                'title' => 'Road to Partner',
                'goal_target' => 50,
                'end_date' => '2026-04-30',
                'sound_preset' => 'chime',
            ],
        ]);
        $widget->followerGoalState()->create([
            'current_count' => 0,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        $signedUrl = URL::signedRoute('overlay.widgets.published', [
            'widget' => $widget->uuid,
            'key' => $widget->publication_key,
        ], absolute: false);

        $this->get($signedUrl)
            ->assertOk()
            ->assertSee('Road to Partner')
            ->assertSee('0 / 50')
            ->assertSee('50 followers to go');
    }
}
