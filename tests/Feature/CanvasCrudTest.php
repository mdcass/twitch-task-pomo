<?php

namespace Tests\Feature;

use App\Actions\Canvases\ArchiveCanvas;
use App\Actions\Canvases\CreateCanvas;
use App\Actions\Canvases\RestoreCanvas;
use App\Actions\Canvases\UpdateCanvas;
use App\Enums\ActivityEvent;
use App\Enums\TeamMemberRole;
use App\Livewire\Canvases\CanvasForm;
use App\Livewire\Canvases\CanvasIndex;
use App\Livewire\Canvases\CanvasLifecycleModal;
use App\Models\Activity;
use App\Models\Canvas;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CanvasCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_canvas_policy_allows_team_members_to_view_and_owner_to_mutate(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $this->assertTrue($owner->can('viewAny', Canvas::class));
        $this->assertTrue($owner->can('view', $canvas));
        $this->assertTrue($owner->can('create', Canvas::class));
        $this->assertTrue($owner->can('update', $canvas));
        $this->assertTrue($owner->can('delete', $canvas));

        $this->assertTrue($member->can('viewAny', Canvas::class));
        $this->assertTrue($member->can('view', $canvas));
        $this->assertFalse($member->can('create', Canvas::class));
        $this->assertFalse($member->can('update', $canvas));
        $this->assertFalse($member->can('delete', $canvas));

        $canvas->delete();

        $this->assertTrue($owner->can('restore', $canvas->fresh()));
        $this->assertFalse($member->can('restore', $canvas->fresh()));
    }

    public function test_canvases_page_renders_and_marks_the_sidebar_item_as_active(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $this->actingAs($user)
            ->get(route('canvases.index', absolute: false))
            ->assertOk()
            ->assertSee('Canvases')
            ->assertSee('data-content-top-shell', false)
            ->assertSee('class="nav-link label-1 active" href="/canvases"', false);
    }

    public function test_canvas_pages_render_breadcrumbs_in_the_content_top_band_without_duplication(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'name' => 'Scene Alpha',
        ]);

        $indexResponse = $this->actingAs($user)->get(route('canvases.index', absolute: false));

        $indexResponse
            ->assertOk()
            ->assertSee('data-content-top-shell', false)
            ->assertSee('data-content-top-nav', false)
            ->assertSee('data-content-top-scroller', false)
            ->assertSeeInOrder(['aria-label="breadcrumb"', '<h1 class="h2 mb-1">Canvases</h1>'], false);

        $this->assertSame(1, substr_count($indexResponse->getContent(), 'aria-label="breadcrumb"'));

        $editResponse = $this->actingAs($user)->get(route('canvases.edit', $canvas, false));

        $editResponse
            ->assertOk()
            ->assertSee('data-content-top-shell', false)
            ->assertSee('data-content-top-nav', false)
            ->assertSee('data-content-top-scroller', false)
            ->assertSeeInOrder(['aria-label="breadcrumb"', 'Canvas editor'], false);

        $this->assertSame(1, substr_count($editResponse->getContent(), 'aria-label="breadcrumb"'));
    }

    public function test_canvas_form_creates_a_canvas_and_redirects_to_the_edit_page(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        Activity::query()->delete();

        Livewire::actingAs($user)
            ->test(CanvasForm::class, [
                'mode' => 'create',
                'offcanvasId' => 'canvas-create-offcanvas',
            ])
            ->set('fields.name', 'Main Scene')
            ->set('fields.width', 1600)
            ->set('fields.height', 900)
            ->call('submit')
            ->assertRedirect();

        $canvas = Canvas::query()->where('name', 'Main Scene')->firstOrFail();

        $this->assertSame($user->current_team_id, $canvas->team_id);
        $this->assertSame($user->id, $canvas->created_by_user_id);
        $this->assertSame(1600, $canvas->width);
        $this->assertSame(900, $canvas->height);

        $activity = Activity::query()
            ->where('event', ActivityEvent::CanvasCreated->value)
            ->sole();

        $this->assertSame('canvas', $activity->log_name);
        $this->assertTrue($activity->causer->is($user));
        $this->assertSame($user->current_team_id, $activity->team_id);
        $this->assertSame('canvases.canvas-form', $activity->getExtraProperty('livewire_component'));
        $this->assertSame('submit', $activity->getExtraProperty('livewire_method'));
    }

    public function test_canvas_form_shows_a_validation_error_when_name_is_blank(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        Livewire::actingAs($user)
            ->test(CanvasForm::class, [
                'mode' => 'create',
                'offcanvasId' => 'canvas-create-offcanvas',
            ])
            ->set('fields.name', '   ')
            ->call('submit')
            ->assertSeeText('The name field is required.')
            ->assertHasErrors(['fields.name'])
            ->assertNoRedirect();

        $this->assertDatabaseCount('canvases', 0);
    }

    public function test_canvas_create_action_rejects_invalid_input_outside_livewire(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        try {
            app(CreateCanvas::class)->create($user, [
                'name' => '   ',
                'width' => 1920,
                'height' => 1080,
            ]);

            $this->fail('Expected validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'The name field is required.',
            ], $exception->errors()['name']);
        }

        $this->assertDatabaseCount('canvases', 0);
    }

    public function test_canvas_form_updates_canvas_and_dispatches_refresh_events(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'name' => 'Before',
        ]);
        Activity::query()->delete();

        Livewire::actingAs($user)
            ->test(CanvasForm::class, [
                'mode' => 'edit',
                'canvasId' => $canvas->id,
                'offcanvasId' => 'canvas-edit-offcanvas',
            ])
            ->set('fields.name', 'After')
            ->set('fields.width', 1280)
            ->set('fields.height', 720)
            ->call('submit')
            ->assertDispatched('canvas-saved')
            ->assertDispatched('overlay-offcanvas-close', id: 'canvas-edit-offcanvas');

        $canvas->refresh();

        $this->assertSame('After', $canvas->name);
        $this->assertSame(1280, $canvas->width);
        $this->assertSame(720, $canvas->height);

        $activity = Activity::query()
            ->where('event', ActivityEvent::CanvasUpdated->value)
            ->sole();

        $this->assertSame('canvas', $activity->log_name);
        $this->assertTrue($activity->causer->is($user));
        $this->assertSame('canvases.canvas-form', $activity->getExtraProperty('livewire_component'));
        $this->assertSame('submit', $activity->getExtraProperty('livewire_method'));
    }

    public function test_canvas_actions_authorize_direct_non_http_calls(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $this->expectException(AuthorizationException::class);

        app(CreateCanvas::class)->create($member, [
            'name' => 'Member Scene',
            'width' => 1280,
            'height' => 720,
        ]);
    }

    public function test_canvas_update_action_authorizes_direct_non_http_calls(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $this->expectException(AuthorizationException::class);

        app(UpdateCanvas::class)->update($member, $canvas, [
            'name' => 'Member Update',
            'width' => 1280,
            'height' => 720,
        ]);
    }

    public function test_canvas_lifecycle_actions_authorize_direct_non_http_calls(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        try {
            app(ArchiveCanvas::class)->archive($member, $canvas);
            $this->fail('ArchiveCanvas should authorize direct callers through the policy gate.');
        } catch (AuthorizationException) {
            $this->assertFalse($canvas->fresh()->trashed());
        }

        $canvas->delete();

        $this->expectException(AuthorizationException::class);

        app(RestoreCanvas::class)->restore($member, $canvas->fresh());
    }

    public function test_index_defaults_to_the_active_filter_and_archived_rows_are_not_clickable(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $activeCanvas = Canvas::factory()->for($user->currentTeam)->create([
            'name' => 'Active Scene',
        ]);
        $archivedCanvas = Canvas::factory()->for($user->currentTeam)->create([
            'name' => 'Archived Scene',
        ]);
        $archivedCanvas->delete();

        Livewire::actingAs($user)
            ->test(CanvasIndex::class)
            ->assertSet('filter', 'active')
            ->assertSeeText('Active Scene')
            ->assertDontSeeText('Archived Scene');

        $this->actingAs($user)
            ->get(route('canvases.index', absolute: false))
            ->assertOk()
            ->assertSee("window.location = '".route('canvases.edit', $activeCanvas, false)."'", false)
            ->assertDontSee("window.location = '".route('canvases.edit', $archivedCanvas, false)."'", false);
    }

    public function test_recent_canvas_hero_only_uses_the_three_most_recent_active_canvases(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $oldest = Canvas::factory()->for($user->currentTeam)->create(['name' => 'Oldest']);
        $middle = Canvas::factory()->for($user->currentTeam)->create(['name' => 'Middle']);
        $newer = Canvas::factory()->for($user->currentTeam)->create(['name' => 'Newer']);
        $newest = Canvas::factory()->for($user->currentTeam)->create(['name' => 'Newest']);
        $archived = Canvas::factory()->for($user->currentTeam)->create(['name' => 'Archived Hero']);

        $oldest->forceFill(['updated_at' => now()->subDays(4)])->save();
        $middle->forceFill(['updated_at' => now()->subDays(3)])->save();
        $newer->forceFill(['updated_at' => now()->subDays(2)])->save();
        $newest->forceFill(['updated_at' => now()->subDay()])->save();
        $archived->forceFill(['updated_at' => now()])->save();
        $archived->delete();

        $component = Livewire::actingAs($user)->test(CanvasIndex::class);

        $this->assertSame(
            ['Newest', 'Newer', 'Middle'],
            $component->instance()->recentCanvases->pluck('name')->all(),
        );
    }

    public function test_canvas_lifecycle_modal_archives_and_restores_canvases(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'name' => 'Lifecycle Scene',
        ]);
        Activity::query()->delete();

        Livewire::actingAs($user)
            ->test(CanvasLifecycleModal::class, [
                'canvasId' => $canvas->id,
                'modalId' => 'canvas-lifecycle-modal',
                'action' => 'archive',
            ])
            ->call('confirm')
            ->assertDispatched('canvas-saved')
            ->assertDispatched('overlay-modal-close', id: 'canvas-lifecycle-modal');

        $this->assertSoftDeleted('canvases', [
            'id' => $canvas->id,
        ]);

        $archiveActivity = Activity::query()
            ->where('event', ActivityEvent::CanvasArchived->value)
            ->sole();

        $this->assertSame('canvas', $archiveActivity->log_name);
        $this->assertTrue($archiveActivity->causer->is($user));
        $this->assertSame('canvases.canvas-lifecycle-modal', $archiveActivity->getExtraProperty('livewire_component'));
        $this->assertSame('confirm', $archiveActivity->getExtraProperty('livewire_method'));

        Activity::query()->delete();

        Livewire::actingAs($user)
            ->test(CanvasLifecycleModal::class, [
                'canvasId' => $canvas->id,
                'modalId' => 'canvas-lifecycle-modal',
                'action' => 'restore',
            ])
            ->call('confirm')
            ->assertDispatched('canvas-saved')
            ->assertDispatched('overlay-modal-close', id: 'canvas-lifecycle-modal');

        $this->assertDatabaseHas('canvases', [
            'id' => $canvas->id,
            'deleted_at' => null,
        ]);

        $restoreActivity = Activity::query()
            ->where('event', ActivityEvent::CanvasRestored->value)
            ->sole();

        $this->assertSame('canvas', $restoreActivity->log_name);
        $this->assertTrue($restoreActivity->causer->is($user));
        $this->assertSame('canvases.canvas-lifecycle-modal', $restoreActivity->getExtraProperty('livewire_component'));
        $this->assertSame('confirm', $restoreActivity->getExtraProperty('livewire_method'));
    }

    public function test_canvas_create_action_preserves_the_explicit_causer_outside_authenticated_requests(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        Activity::query()->delete();

        $canvas = app(CreateCanvas::class)->create($user, [
            'name' => 'Action Scene',
            'width' => 1280,
            'height' => 720,
        ]);

        $activity = Activity::query()
            ->where('event', ActivityEvent::CanvasCreated->value)
            ->sole();

        $this->assertTrue($activity->subject->is($canvas));
        $this->assertTrue($activity->causer->is($user));
    }

    public function test_team_members_can_view_active_canvas_edit_page_but_archived_canvas_routes_are_missing(): void
    {
        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        $activeCanvas = Canvas::factory()->for($owner->currentTeam)->create([
            'name' => 'Shared Scene',
        ]);
        $archivedCanvas = Canvas::factory()->for($owner->currentTeam)->create([
            'name' => 'Archived Scene',
        ]);
        $archivedCanvas->delete();

        $this->actingAs($member)
            ->get(route('canvases.edit', $activeCanvas, false))
            ->assertOk()
            ->assertSee('Shared Scene');

        $this->actingAs($member)
            ->get('/canvases/'.$archivedCanvas->id.'/edit')
            ->assertNotFound();
    }

    public function test_canvas_index_dispatches_overlay_events_for_the_configured_hosts(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'name' => 'Scene Alpha',
        ]);

        Livewire::actingAs($user)
            ->test(CanvasIndex::class)
            ->call('openEditOffcanvas', $canvas->id)
            ->assertDispatched(
                'overlay-offcanvas-load',
                id: 'canvas-edit-offcanvas',
                title: 'Edit Canvas',
                data: ['canvasId' => $canvas->id],
            )
            ->call('confirmLifecycleAction', $canvas->id, 'archive')
            ->assertDispatched(
                'overlay-modal-load',
                id: 'canvas-lifecycle-modal',
                title: 'Archive Canvas',
                data: [
                    'canvasId' => $canvas->id,
                    'action' => 'archive',
                ],
            );
    }
}
