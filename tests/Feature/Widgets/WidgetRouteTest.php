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
            'lifecycle_state' => WidgetLifecycleState::PendingConnection,
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
            ->assertSee('Manage reusable proprietary widgets for standalone URLs and canvas placement.')
            ->assertSee('Focus Queue')
            ->assertDontSee('Audience Milestone')
            ->assertDontSee('Remote Countdown');
    }

    public function test_widget_store_route_creates_widgets_for_owners_and_validates_input(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $response = $this->actingAs($owner)->post(route('widgets.store', absolute: false), [
            'type' => 'pomodoro',
            'name' => '  Sprint Timer  ',
        ]);

        $widget = Widget::query()->sole();

        $response
            ->assertRedirect(route('widgets.show', $widget, false))
            ->assertSessionHas('status', 'Widget created.');

        $this->assertSame('Sprint Timer', $widget->name);

        $this->actingAs($owner)
            ->from(route('widgets.index', absolute: false))
            ->post(route('widgets.store', absolute: false), [
                'type' => 'not-a-widget',
            ])
            ->assertRedirect(route('widgets.index', absolute: false))
            ->assertSessionHasErrors(['type']);

        $this->actingAs($member)
            ->post(route('widgets.store', absolute: false), [
                'type' => 'task_list',
            ])
            ->assertForbidden();
    }

    public function test_widget_show_route_allows_team_members_and_blocks_outsiders(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->withStreamerTeam()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create([
            'name' => 'Partner Push',
            'lifecycle_state' => WidgetLifecycleState::PendingConnection,
        ]);
        $widget->followerGoalState()->create([
            'current_count' => 4,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        $this->actingAs($member)
            ->get(route('widgets.show', $widget, false))
            ->assertOk()
            ->assertSee('Partner Push')
            ->assertSee('Owner action required: connect Twitch')
            ->assertSee('Reusable proprietary widget. Remote embeds stay canvas-scoped and are managed from canvas pages only.');

        $this->actingAs($outsider)
            ->get(route('widgets.show', $widget, false))
            ->assertForbidden();
    }

    public function test_widget_mutation_routes_apply_state_changes_for_owners(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->taskList()->create([
            'name' => 'Focus Queue',
            'lifecycle_state' => WidgetLifecycleState::Ready,
            'published_at' => null,
            'publication_key' => null,
        ]);
        $followerGoal = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create([
            'lifecycle_state' => WidgetLifecycleState::PendingConnection,
        ]);
        $followerGoal->followerGoalState()->create([
            'current_count' => 9,
            'frozen_at' => now(),
            'last_followed_at' => now()->subMinute(),
        ]);

        $this->actingAs($owner)
            ->patch(route('widgets.update', $widget, false), [
                'name' => 'Updated Focus Queue',
                'config' => [
                    'title' => 'Evening Queue',
                    'pending' => ['Plan stream'],
                    'completed' => [],
                ],
                'appearance' => [
                    'accent' => 'success',
                ],
            ])
            ->assertRedirect(route('widgets.show', $widget, false))
            ->assertSessionHas('status', 'Widget updated.');

        $widget->refresh();
        $this->assertSame('Updated Focus Queue', $widget->name);
        $this->assertSame('Evening Queue', $widget->config['title']);
        $this->assertSame('success', $widget->appearance['accent']);

        $this->actingAs($owner)
            ->post(route('widgets.publish', $widget, false))
            ->assertRedirect(route('widgets.show', $widget, false))
            ->assertSessionHas('status', 'Widget published.');

        $widget->refresh();
        $publishedKey = $widget->publication_key;
        $this->assertTrue($widget->isPublished());

        $this->actingAs($owner)
            ->post(route('widgets.rotate-publication-key', $widget, false))
            ->assertRedirect(route('widgets.show', $widget, false))
            ->assertSessionHas('status', 'Widget URL regenerated.');

        $widget->refresh();
        $this->assertNotSame($publishedKey, $widget->publication_key);

        $this->actingAs($owner)
            ->post(route('widgets.unpublish', $widget, false))
            ->assertRedirect(route('widgets.show', $widget, false))
            ->assertSessionHas('status', 'Widget unpublished.');

        $this->assertNull($widget->fresh()->published_at);

        $this->actingAs($owner)
            ->post(route('widgets.archive', $widget, false))
            ->assertRedirect(route('widgets.show', $widget, false))
            ->assertSessionHas('status', 'Widget archived.');

        $this->assertSame(WidgetLifecycleState::Archived, $widget->fresh()->lifecycle_state);

        $this->actingAs($owner)
            ->post(route('widgets.restore', $widget, false))
            ->assertRedirect(route('widgets.show', $widget, false))
            ->assertSessionHas('status', 'Widget restored.');

        $this->assertSame(WidgetLifecycleState::Ready, $widget->fresh()->lifecycle_state);

        $this->actingAs($owner)
            ->post(route('widgets.reset-follower-goal', $followerGoal, false))
            ->assertRedirect(route('widgets.show', $followerGoal, false))
            ->assertSessionHas('status', 'Follower Goal reset.');

        $this->assertSame(0, $followerGoal->fresh('followerGoalState')->followerGoalState->current_count);
    }

    public function test_widget_mutation_routes_validate_requests_and_forbid_team_members(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->taskList()->create();
        $followerGoal = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create();
        $followerGoal->followerGoalState()->create([
            'current_count' => 1,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $this->actingAs($owner)
            ->from(route('widgets.show', $widget, false))
            ->patch(route('widgets.update', $widget, false), [
                'name' => '',
                'config' => [],
            ])
            ->assertRedirect(route('widgets.show', $widget, false))
            ->assertSessionHasErrors(['name', 'appearance']);

        foreach ([
            ['method' => 'patch', 'route' => route('widgets.update', $widget, false), 'payload' => [
                'name' => 'Blocked',
                'config' => ['title' => 'Blocked', 'pending' => [], 'completed' => []],
                'appearance' => ['accent' => 'primary'],
            ]],
            ['method' => 'post', 'route' => route('widgets.publish', $widget, false), 'payload' => []],
            ['method' => 'post', 'route' => route('widgets.unpublish', $widget, false), 'payload' => []],
            ['method' => 'post', 'route' => route('widgets.rotate-publication-key', $widget, false), 'payload' => []],
            ['method' => 'post', 'route' => route('widgets.archive', $widget, false), 'payload' => []],
            ['method' => 'post', 'route' => route('widgets.restore', $widget, false), 'payload' => []],
            ['method' => 'post', 'route' => route('widgets.reset-follower-goal', $followerGoal, false), 'payload' => []],
        ] as $request) {
            $this->actingAs($member)
                ->{$request['method']}($request['route'], $request['payload'])
                ->assertForbidden();
        }
    }

    public function test_published_widget_overlay_route_requires_a_matching_key_and_a_non_archived_widget(): void
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
            'lifecycle_state' => WidgetLifecycleState::Archived,
        ])->save();

        $archivedUrl = URL::signedRoute('overlay.widgets.published', [
            'widget' => $widget->uuid,
            'key' => $widget->publication_key,
        ], absolute: false);

        $this->get($archivedUrl)->assertNotFound();
    }
}
