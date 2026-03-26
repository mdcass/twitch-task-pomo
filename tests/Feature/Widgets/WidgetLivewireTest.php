<?php

namespace Tests\Feature\Widgets;

use App\Enums\Models\WidgetLifecycleState;
use App\Enums\Models\WidgetType;
use App\Enums\TeamMemberRole;
use App\Livewire\Canvases\AttachExistingWidgetForm;
use App\Livewire\Canvases\EditWidgetSettingsForm;
use App\Livewire\Widgets\WidgetIndex;
use App\Livewire\Widgets\WidgetEditor;
use App\Models\Canvas;
use App\Models\User;
use App\Models\Widget;
use App\Models\WidgetInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WidgetLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_index_creates_the_requested_widget_and_redirects_to_the_editor(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        Livewire::actingAs($user)
            ->test(WidgetIndex::class)
            ->set('fields.type', WidgetType::FollowerGoal->value)
            ->set('fields.name', '  Partner Push  ')
            ->call('create')
            ->assertRedirect(route('widgets.edit', Widget::query()->sole(), false));

        $widget = Widget::query()->sole();

        $this->assertSame('Partner Push', $widget->name);
        $this->assertSame(WidgetType::FollowerGoal, $widget->type);
        $this->assertSame(WidgetLifecycleState::PendingConnection, $widget->lifecycle_state);
        $this->assertNotNull($widget->followerGoalState);
    }

    public function test_widget_index_validates_widget_types_and_denies_non_owners(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        Livewire::actingAs($owner)
            ->test(WidgetIndex::class)
            ->set('fields.type', 'not-a-widget')
            ->call('create')
            ->assertHasErrors(['fields.type']);

        Livewire::actingAs($member)
            ->test(WidgetIndex::class)
            ->assertDontSee('Create widget')
            ->set('fields.type', WidgetType::TaskList->value)
            ->call('create')
            ->assertForbidden();
    }

    public function test_widget_index_filters_the_library_using_livewire_state(): void
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

        Livewire::actingAs($member)
            ->test(WidgetIndex::class)
            ->set('search', 'Focus')
            ->set('type', $matchingWidget->type->value)
            ->set('lifecycle', WidgetLifecycleState::Ready->value)
            ->assertSee('Focus Queue')
            ->assertDontSee('Audience Milestone')
            ->assertDontSee('Remote Countdown');
    }

    public function test_widget_editor_groups_save_publication_archive_and_goal_reset_behaviors(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $widget = Widget::factory()->forTeam($user->currentTeam, $user)->followerGoal()->create([
            'name' => 'Partner Push',
        ]);
        $widget->followerGoalState()->create([
            'current_count' => 14,
            'frozen_at' => now(),
            'last_followed_at' => now()->subMinute(),
        ]);

        $component = Livewire::actingAs($user)
            ->test(WidgetEditor::class, ['widgetId' => $widget->id])
            ->assertSee('Connect Twitch')
            ->set('fields.name', 'Updated Partner Push')
            ->set('fields.config.title', 'Road to Partner')
            ->set('fields.config.goal_target', 100)
            ->set('fields.config.end_date', '2026-04-30')
            ->set('fields.config.sound_preset', 'none')
            ->set('fields.appearance.accent', 'warning')
            ->call('save');

        $widget->refresh();
        $this->assertSame('Updated Partner Push', $widget->name);
        $this->assertSame('Road to Partner', $widget->config['title']);
        $this->assertSame(100, $widget->config['goal_target']);
        $this->assertSame('warning', $widget->appearance['accent']);

        $component->call('publish')
            ->assertSee('Standalone URL turned on.');
        $widget->refresh();
        $firstPublicationKey = $widget->publication_key;
        $this->assertTrue($widget->isPublished());

        $component->call('regenerate')
            ->assertSee('Standalone URL regenerated.');
        $widget->refresh();
        $this->assertNotSame($firstPublicationKey, $widget->publication_key);

        $component->call('archive')
            ->assertSee('Widget archived.');
        $this->assertSame(WidgetLifecycleState::Archived, $widget->fresh()->lifecycle_state);

        $component->call('restore')
            ->assertSee('Widget restored.');
        $widget->refresh();
        $this->assertSame(WidgetLifecycleState::PendingConnection, $widget->lifecycle_state);

        $component->call('resetFollowerGoal')
            ->assertSee('Follower Goal reset.');
        $widget->refresh();
        $this->assertSame(0, $widget->followerGoalState->current_count);
        $this->assertNull($widget->followerGoalState->frozen_at);

        $component->call('unpublish')
            ->assertSee('Standalone URL turned off.');
        $this->assertNull($widget->fresh()->published_at);
    }

    public function test_widget_editor_renders_a_view_only_state_for_team_members(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create();
        $widget->followerGoalState()->create([
            'current_count' => 2,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        Livewire::actingAs($member)
            ->test(WidgetEditor::class, ['widgetId' => $widget->id])
            ->assertSee($widget->displayName())
            ->assertSee('Needs Setup')
            ->assertSee('Standalone URL')
            ->assertDontSee('Save changes')
            ->assertDontSee('Connect Twitch')
            ->assertDontSee('Turn Standalone URL On');
    }

    public function test_attach_widget_form_validates_input_and_attaches_widgets_to_the_canvas(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();
        $widget = Widget::factory()->forTeam($owner->currentTeam, $owner)->taskList()->ready()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        Livewire::actingAs($owner)
            ->test(AttachExistingWidgetForm::class, [
                'canvasId' => $canvas->id,
                'offcanvasId' => 'attach-existing-widget-offcanvas',
            ])
            ->call('submit')
            ->assertHasErrors(['fields.widget_id']);

        Livewire::actingAs($owner)
            ->test(AttachExistingWidgetForm::class, [
                'canvasId' => $canvas->id,
                'offcanvasId' => 'attach-existing-widget-offcanvas',
            ])
            ->set('fields.widget_id', $widget->id)
            ->call('submit')
            ->assertDispatched('widget-created')
            ->assertDispatched('overlay-offcanvas-close', id: 'attach-existing-widget-offcanvas');

        $this->assertDatabaseHas('canvas_widgets', [
            'canvas_id' => $canvas->id,
            'widget_id' => $widget->id,
            'source_kind' => 'proprietary',
        ]);

        Livewire::actingAs($member)
            ->test(AttachExistingWidgetForm::class, [
                'canvasId' => $canvas->id,
            ])
            ->set('fields.widget_id', $widget->id)
            ->call('submit')
            ->assertForbidden();
    }

    public function test_edit_widget_settings_form_loads_task_list_fields_and_keeps_provider_actions_on_the_full_page(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $taskList = Widget::factory()->forTeam($owner->currentTeam, $owner)->taskList()->ready()->create([
            'name' => 'Shared Queue',
            'config' => [
                'title' => 'Focus Queue',
                'pending' => ['Plan stream', 'Review notes'],
                'completed' => ['Warmup'],
            ],
        ]);
        $providerBacked = Widget::factory()->forTeam($owner->currentTeam, $owner)->followerGoal()->create([
            'lifecycle_state' => WidgetLifecycleState::PendingConnection,
        ]);
        $providerBacked->followerGoalState()->create([
            'current_count' => 3,
            'frozen_at' => null,
            'last_followed_at' => null,
        ]);
        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        Livewire::actingAs($owner)
            ->test(EditWidgetSettingsForm::class, [
                'offcanvasId' => 'edit-widget-settings-offcanvas',
            ])
            ->call('loaded', ['data' => ['widgetId' => $taskList->id]])
            ->assertSet('fields.name', 'Shared Queue')
            ->assertSet('fields.config.pending_text', "Plan stream\nReview notes")
            ->assertSet('fields.config.completed_text', 'Warmup')
            ->set('fields.name', 'Updated Shared Queue')
            ->set('fields.config.title', 'Updated Queue')
            ->set('fields.config.pending_text', "Write outline\nTrim VOD")
            ->set('fields.config.completed_text', 'Warmup')
            ->set('fields.appearance.accent', 'success')
            ->call('submit')
            ->assertDispatched('widget-updated', widgetId: $taskList->id)
            ->assertDispatched('overlay-offcanvas-close', id: 'edit-widget-settings-offcanvas');

        $taskList->refresh();
        $this->assertSame('Updated Shared Queue', $taskList->name);
        $this->assertSame('Updated Queue', $taskList->config['title']);
        $this->assertSame(['Write outline', 'Trim VOD'], $taskList->config['pending']);
        $this->assertSame(['Warmup'], $taskList->config['completed']);
        $this->assertSame('success', $taskList->appearance['accent']);

        Livewire::actingAs($owner)
            ->test(EditWidgetSettingsForm::class)
            ->call('loaded', ['data' => ['widgetId' => $providerBacked->id]])
            ->assertSee('Provider connect, repair, standalone URL, and archive actions stay on the full widget page.')
            ->assertDontSee('Turn Standalone URL On')
            ->assertDontSee('Archive');

        Livewire::actingAs($member)
            ->test(EditWidgetSettingsForm::class)
            ->call('loaded', ['data' => ['widgetId' => $taskList->id]])
            ->assertForbidden();
    }

    public function test_spotify_now_playing_ignores_widgets_outside_the_current_team(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $foreignOwner = User::factory()->withStreamerTeam()->create();
        $foreignWidget = Widget::factory()->forTeam($foreignOwner->currentTeam, $foreignOwner)->spotifyNowPlaying()->create();

        Livewire::actingAs($owner)
            ->test(\App\Livewire\Widgets\SpotifyNowPlaying::class, [
                'widgetId' => $foreignWidget->id,
            ])
            ->assertSet('status', 'idle')
            ->assertSet('message', 'Loading Spotify preview...')
            ->assertSet('track.title', null)
            ->assertSet('source', null);
    }
}
