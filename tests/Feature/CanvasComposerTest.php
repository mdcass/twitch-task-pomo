<?php

namespace Tests\Feature;

use App\Actions\WidgetInstances\QuickCreateWidget;
use App\Actions\WidgetInstances\CreateRemoteWidget;
use App\Actions\WidgetInstances\DeleteWidgetInstance;
use App\Actions\WidgetInstances\RestoreWidgetInstanceState;
use App\Actions\WidgetInstances\UpdateWidgetGeometry;
use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Enums\Models\WidgetType;
use App\Enums\TeamMemberRole;
use App\Livewire\Canvases\AddRemoteWidgetForm;
use App\Livewire\Canvases\CanvasComposer;
use App\Livewire\Canvases\QuickCreateWidgetForm;
use App\Livewire\Canvases\WidgetDeleteModal;
use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class CanvasComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_instance_schema_includes_content_and_crop_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('canvas_widgets', [
            'content_width',
            'content_height',
            'crop_top',
            'crop_right',
            'crop_bottom',
            'crop_left',
        ]));
    }

    public function test_canvas_edit_page_renders_the_composer_workspace(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'name' => 'Composer Scene',
        ]);

        WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'name' => 'Task Layer',
        ]);

        $this->actingAs($user)
            ->get(route('canvases.edit', $canvas, false))
            ->assertOk()
            ->assertSee('Layers')
            ->assertSee('Selected widget')
            ->assertSee('data-widget-add', false)
            ->assertSee('data-composer-editor', false)
            ->assertSee('data-composer-stage', false)
            ->assertSee('data-composer-snapshot', false)
            ->assertSee('Task List');
    }

    public function test_quick_create_widget_form_creates_a_proprietary_widget_placement(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test(QuickCreateWidgetForm::class, [
                'canvasId' => $canvas->id,
                'offcanvasId' => 'canvas-quick-create-widget-offcanvas',
            ])
            ->set('fields.type', WidgetType::Pomodoro->value)
            ->call('submit')
            ->assertDispatched('widget-created')
            ->assertDispatched('overlay-offcanvas-close', id: 'canvas-quick-create-widget-offcanvas');

        $widget = $canvas->fresh()->widgetInstances()->sole();

        $this->assertSame(WidgetSourceKind::Proprietary, $widget->source_kind);
        $this->assertSame(WidgetType::Pomodoro, $widget->type);
        $this->assertSame('Pomodoro Timer', $widget->displayName());
        $this->assertTrue($widget->is_visible);
        $this->assertSame(520, $widget->width);
        $this->assertSame(320, $widget->height);
        $this->assertSame(520, $widget->content_width);
        $this->assertSame(320, $widget->content_height);
        $this->assertSame(0, $widget->crop_top);
        $this->assertSame(0, $widget->crop_right);
        $this->assertSame(0, $widget->crop_bottom);
        $this->assertSame(0, $widget->crop_left);
        $this->assertSame([
            'frame_width' => 520,
            'frame_height' => 320,
            'content_width' => 520,
            'content_height' => 320,
        ], $widget->settings['editor_defaults'] ?? null);
    }

    public function test_remote_widget_form_rejects_non_https_urls(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test(AddRemoteWidgetForm::class, [
                'canvasId' => $canvas->id,
                'offcanvasId' => 'canvas-remote-widget-offcanvas',
            ])
            ->set('fields.name', 'Insecure Widget')
            ->set('fields.embed_url', 'http://example.com/widget')
            ->call('submit')
            ->assertHasErrors(['fields.embed_url']);

        $this->assertDatabaseCount('canvas_widgets', 0);
    }

    public function test_remote_widget_creation_stores_preflight_blocked_state(): void
    {
        Http::fake([
            'https://widgets.example.com/embed' => Http::response('', 200, [
                'X-Frame-Options' => 'DENY',
            ]),
        ]);

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();

        app(CreateRemoteWidget::class)->create($user, $canvas, [
            'name' => 'Blocked Widget',
            'embed_url' => 'https://widgets.example.com/embed',
        ]);

        $widget = $canvas->fresh()->widgetInstances()->sole();

        $this->assertSame(WidgetSourceKind::RemoteUrl, $widget->source_kind);
        $this->assertSame(WidgetPreviewStatus::Blocked, $widget->preview_status);
        $this->assertSame('Remote widget blocks iframe embedding.', $widget->preview_message);
        $this->assertSame([
            'frame_width' => 760,
            'frame_height' => 480,
            'content_width' => 760,
            'content_height' => 480,
        ], $widget->settings['editor_defaults'] ?? null);
    }

    public function test_canvas_composer_updates_geometry_visibility_and_stacking(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $first = WidgetInstance::factory()->for($canvas)->taskList()->create([
            'team_id' => $user->currentTeam->id,
            'z_index' => 0,
        ]);
        $second = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $user->currentTeam->id,
            'z_index' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id])
            ->call('saveGeometry', $first->id, [
                'position_x' => 240,
                'position_y' => 180,
                'width' => 880,
                'height' => 420,
                'content_width' => 960,
                'content_height' => 640,
                'crop_top' => 24,
                'crop_right' => 60,
                'crop_bottom' => 80,
                'crop_left' => 40,
            ])
            ->assertSee('240, 180')
            ->call('toggleVisibility', $first->id)
            ->call('reorderWidget', $second->id, 'back');

        $first->refresh();
        $second->refresh();

        $this->assertSame(240, $first->position_x);
        $this->assertSame(180, $first->position_y);
        $this->assertSame(880, $first->width);
        $this->assertSame(420, $first->height);
        $this->assertSame(960, $first->content_width);
        $this->assertSame(640, $first->content_height);
        $this->assertSame(24, $first->crop_top);
        $this->assertSame(60, $first->crop_right);
        $this->assertSame(80, $first->crop_bottom);
        $this->assertSame(40, $first->crop_left);
        $this->assertFalse($first->is_visible);
        $this->assertSame(0, $second->z_index);
        $this->assertSame(1, $first->z_index);
    }

    public function test_canvas_composer_exposes_a_stage_snapshot_for_the_js_owned_canvas(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'width' => 1080,
            'height' => 1920,
        ]);
        $widget = WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
            'team_id' => $user->currentTeam->id,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
        ]);

        $component = Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id]);

        $snapshot = $component->instance()->stageSnapshot();

        $this->assertSame($widget->id, $snapshot['selectedWidgetId']);
        $this->assertTrue($snapshot['canEdit']);
        $this->assertSame(1080, $snapshot['canvasWidth']);
        $this->assertSame(1920, $snapshot['canvasHeight']);
        $this->assertCount(1, $snapshot['widgets']);
        $this->assertSame($widget->id, $snapshot['widgets'][0]['id']);
        $this->assertTrue($snapshot['widgets'][0]['isVisible']);
        $this->assertSame($widget->content_width, $snapshot['widgets'][0]['contentWidth']);
        $this->assertSame($widget->content_height, $snapshot['widgets'][0]['contentHeight']);
        $this->assertSame($widget->crop_top, $snapshot['widgets'][0]['cropTop']);
        $this->assertSame($widget->crop_right, $snapshot['widgets'][0]['cropRight']);
        $this->assertSame($widget->crop_bottom, $snapshot['widgets'][0]['cropBottom']);
        $this->assertSame($widget->crop_left, $snapshot['widgets'][0]['cropLeft']);
        $this->assertSame(WidgetPreviewStatus::Ready->value, $snapshot['widgets'][0]['previewStatus']);
        $this->assertIsString($snapshot['widgets'][0]['previewUrl']);
        $this->assertIsString($snapshot['widgets'][0]['previewToken']);
    }

    public function test_proprietary_widgets_render_from_signed_overlay_iframe_previews(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $user->currentTeam->id,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
        ]);

        $component = Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id]);

        $previewUrl = $component->instance()->previewUrlFor($widget->fresh());
        $previewToken = $component->instance()->previewTokenFor($widget->fresh());
        $stageSnapshot = $component->instance()->stageSnapshot();

        $this->assertIsString($previewUrl);
        $this->assertIsString($previewToken);
        $this->assertSame($previewToken, $stageSnapshot['widgets'][0]['previewToken']);
        $this->assertStringStartsWith('https://overlay.twitch-task-pomo.test/overlay/widgets/'.$widget->id, $previewUrl);
        $this->assertStringContainsString('signature=', $previewUrl);
        $this->assertStringContainsString('token='.rawurlencode($previewToken), $previewUrl);

        $this->get($previewUrl)
            ->assertOk()
            ->assertSee('overlay-widget-card--pomodoro', false);
    }

    public function test_editor_preview_urls_remain_stable_across_livewire_rerenders(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $first = WidgetInstance::factory()->for($canvas)->taskList()->create([
            'team_id' => $user->currentTeam->id,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
        ]);
        $second = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $user->currentTeam->id,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
        ]);

        $component = Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id]);

        $originalUrl = $component->instance()->previewUrlFor($first->fresh());

        $component
            ->call('selectWidget', $second->id)
            ->call('saveGeometry', $first->id, [
                'position_x' => 180,
                'position_y' => 120,
                'width' => 640,
                'height' => 360,
                'content_width' => 640,
                'content_height' => 360,
                'crop_top' => 0,
                'crop_right' => 0,
                'crop_bottom' => 0,
                'crop_left' => 0,
            ]);

        $rerenderedUrl = $component->instance()->previewUrlFor($first->fresh());

        $this->assertSame($originalUrl, $rerenderedUrl);
        $this->assertStringNotContainsString('expires=', (string) $originalUrl);
    }

    public function test_canvas_composer_uses_persisted_preflight_fallback_when_no_runtime_session_exists(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();

        WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
            'team_id' => $user->currentTeam->id,
            'preview_status' => WidgetPreviewStatus::Blocked,
            'preview_message' => 'Remote widget blocks iframe embedding.',
        ]);

        Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id])
            ->assertSee('Remote widget blocks iframe embedding.')
            ->assertSee('Preflight')
            ->assertDontSee('data-widget-preview-token', false);
    }

    public function test_widget_geometry_normalization_clamps_to_the_owning_canvas_dimensions(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'width' => 640,
            'height' => 960,
        ]);
        $widget = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'position_x' => 80,
            'position_y' => 60,
            'width' => 320,
            'height' => 240,
            'content_width' => 320,
            'content_height' => 240,
        ]);

        $updated = app(UpdateWidgetGeometry::class)->update($user, $widget, [
            'position_x' => 500,
            'position_y' => 400,
            'width' => 600,
            'height' => 900,
            'content_width' => 3840,
            'content_height' => 2160,
        ]);

        $this->assertSame(40, $updated->position_x);
        $this->assertSame(60, $updated->position_y);
        $this->assertSame(600, $updated->width);
        $this->assertSame(900, $updated->height);
        $this->assertSame(3840, $updated->content_width);
        $this->assertSame(2160, $updated->content_height);
    }

    public function test_widget_geometry_normalization_supports_canvases_smaller_than_the_default_widget_minimums(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'width' => 80,
            'height' => 60,
        ]);
        $widget = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'width' => 80,
            'height' => 60,
            'content_width' => 80,
            'content_height' => 60,
        ]);

        $updated = app(UpdateWidgetGeometry::class)->update($user, $widget, [
            'position_x' => 40,
            'position_y' => 30,
            'width' => 80,
            'height' => 60,
            'content_width' => 320,
            'content_height' => 240,
        ]);

        $this->assertSame(0, $updated->position_x);
        $this->assertSame(0, $updated->position_y);
        $this->assertSame(80, $updated->width);
        $this->assertSame(60, $updated->height);
        $this->assertSame(320, $updated->content_width);
        $this->assertSame(240, $updated->content_height);
    }

    public function test_widget_geometry_normalization_leaves_visible_content_after_crop(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'content_width' => 640,
            'content_height' => 360,
        ]);

        $updated = app(UpdateWidgetGeometry::class)->update($user, $widget, [
            'crop_left' => 500,
            'crop_right' => 500,
            'crop_top' => 220,
            'crop_bottom' => 220,
        ]);

        $this->assertSame(520, $updated->crop_left + $updated->crop_right);
        $this->assertSame(270, $updated->crop_top + $updated->crop_bottom);
        $this->assertSame(120, $updated->visibleContentWidth());
        $this->assertSame(90, $updated->visibleContentHeight());
    }

    public function test_widget_geometry_normalization_clamps_crop_when_source_bounds_shrink(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'content_width' => 640,
            'content_height' => 360,
            'crop_left' => 160,
            'crop_right' => 160,
            'crop_top' => 60,
            'crop_bottom' => 60,
        ]);

        $updated = app(UpdateWidgetGeometry::class)->update($user, $widget, [
            'content_width' => 240,
            'content_height' => 120,
        ]);

        $this->assertSame(120, $updated->crop_left + $updated->crop_right);
        $this->assertSame(30, $updated->crop_top + $updated->crop_bottom);
        $this->assertSame(120, $updated->visibleContentWidth());
        $this->assertSame(90, $updated->visibleContentHeight());
    }

    public function test_canvas_composer_can_reset_crop_geometry(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'crop_top' => 24,
            'crop_right' => 36,
            'crop_bottom' => 48,
            'crop_left' => 60,
            'content_width' => 720,
            'content_height' => 480,
        ]);

        Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id])
            ->call('resetGeometry', $widget->id, 'crop');

        $widget->refresh();

        $this->assertSame(0, $widget->crop_top);
        $this->assertSame(0, $widget->crop_right);
        $this->assertSame(0, $widget->crop_bottom);
        $this->assertSame(0, $widget->crop_left);
        $this->assertSame(720, $widget->content_width);
        $this->assertSame(480, $widget->content_height);
    }

    public function test_canvas_composer_can_reset_source_geometry_to_editor_defaults(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'width' => 520,
            'height' => 320,
            'content_width' => 920,
            'content_height' => 640,
            'crop_top' => 24,
            'crop_right' => 40,
            'crop_bottom' => 32,
            'crop_left' => 48,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 640,
                    'frame_height' => 360,
                    'content_width' => 640,
                    'content_height' => 360,
                ],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id])
            ->call('resetGeometry', $widget->id, 'source');

        $widget->refresh();

        $this->assertSame(520, $widget->width);
        $this->assertSame(320, $widget->height);
        $this->assertSame(640, $widget->content_width);
        $this->assertSame(360, $widget->content_height);
        $this->assertSame(0, $widget->crop_top);
        $this->assertSame(0, $widget->crop_right);
        $this->assertSame(0, $widget->crop_bottom);
        $this->assertSame(0, $widget->crop_left);
    }

    public function test_canvas_composer_can_reset_aspect_geometry_from_visible_source_ratio(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $user->currentTeam->id,
            'position_x' => 120,
            'position_y' => 160,
            'width' => 500,
            'height' => 500,
            'content_width' => 640,
            'content_height' => 360,
        ]);

        Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id])
            ->call('resetGeometry', $widget->id, 'aspect');

        $widget->refresh();

        $this->assertSame(120, $widget->position_x);
        $this->assertSame(160, $widget->position_y);
        $this->assertSame(500, $widget->width);
        $this->assertSame(281, $widget->height);
    }

    public function test_widget_delete_modal_removes_the_widget_and_selects_the_nearest_remaining_layer(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $first = WidgetInstance::factory()->for($canvas)->taskList()->create([
            'team_id' => $user->currentTeam->id,
            'z_index' => 0,
            'name' => 'First Layer',
        ]);
        $second = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $user->currentTeam->id,
            'z_index' => 1,
            'name' => 'Second Layer',
        ]);
        $third = WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
            'team_id' => $user->currentTeam->id,
            'z_index' => 2,
            'name' => 'Third Layer',
        ]);

        Livewire::actingAs($user)
            ->test(WidgetDeleteModal::class, [
                'widgetId' => $second->id,
                'modalId' => 'canvas-widget-delete-modal',
            ])
            ->call('confirm')
            ->assertDispatched('widget-deleted', deletedWidgetId: $second->id, selectedWidgetId: $third->id)
            ->assertDispatched('overlay-modal-close', id: 'canvas-widget-delete-modal');

        $this->assertDatabaseMissing('canvas_widgets', ['id' => $second->id]);
        $this->assertSame(0, $first->fresh()->z_index);
        $this->assertSame(1, $third->fresh()->z_index);
    }

    public function test_canvas_composer_can_restore_a_history_snapshot_for_non_destructive_changes(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $first = WidgetInstance::factory()->for($canvas)->taskList()->create([
            'team_id' => $user->currentTeam->id,
            'z_index' => 0,
            'position_x' => 80,
            'position_y' => 100,
            'width' => 520,
            'height' => 320,
        ]);
        $second = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $user->currentTeam->id,
            'z_index' => 1,
            'position_x' => 200,
            'position_y' => 240,
            'width' => 400,
            'height' => 260,
            'is_visible' => false,
        ]);

        $historySnapshot = [
            [
                'id' => $second->id,
                'position_x' => 120,
                'position_y' => 160,
                'width' => 360,
                'height' => 220,
                'content_width' => 480,
                'content_height' => 300,
                'crop_top' => 10,
                'crop_right' => 12,
                'crop_bottom' => 14,
                'crop_left' => 16,
                'is_visible' => true,
            ],
            [
                'id' => $first->id,
                'position_x' => 320,
                'position_y' => 200,
                'width' => 640,
                'height' => 420,
                'content_width' => 720,
                'content_height' => 480,
                'crop_top' => 20,
                'crop_right' => 24,
                'crop_bottom' => 28,
                'crop_left' => 32,
                'is_visible' => false,
            ],
        ];

        Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id])
            ->call('restoreHistoryState', $historySnapshot, $first->id)
            ->assertSet('selectedWidgetId', $first->id);

        $first->refresh();
        $second->refresh();

        $this->assertSame(1, $first->z_index);
        $this->assertFalse($first->is_visible);
        $this->assertSame(320, $first->position_x);
        $this->assertSame(200, $first->position_y);
        $this->assertSame(32, $first->crop_left);

        $this->assertSame(0, $second->z_index);
        $this->assertTrue($second->is_visible);
        $this->assertSame(120, $second->position_x);
        $this->assertSame(160, $second->position_y);
        $this->assertSame(14, $second->crop_bottom);
    }

    public function test_overlay_canvas_renders_scaled_and_cropped_iframes(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $user->currentTeam->id,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
            'width' => 360,
            'height' => 180,
            'content_width' => 720,
            'content_height' => 360,
            'crop_top' => 20,
            'crop_right' => 40,
            'crop_bottom' => 20,
            'crop_left' => 20,
        ]);

        $signedUrl = URL::signedRoute('overlay.canvases.show', [
            'canvasUuid' => $canvas->uuid,
        ], absolute: false);

        $this->get($signedUrl)
            ->assertOk()
            ->assertSee('overlay-runtime__offset', false)
            ->assertSee('overlay-runtime__scale', false)
            ->assertSee('translate(-10.909091px, -11.250000px)', false)
            ->assertSee('scale(0.545455, 0.562500)', false)
            ->assertSee('width: 720px; height: 360px;', false);
    }

    public function test_overlay_canvas_uses_the_canvas_dimensions_as_the_runtime_surface(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create([
            'width' => 1080,
            'height' => 1920,
        ]);
        WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $user->currentTeam->id,
            'position_x' => 120,
            'position_y' => 160,
            'width' => 360,
            'height' => 180,
        ]);

        $signedUrl = URL::signedRoute('overlay.canvases.show', [
            'canvasUuid' => $canvas->uuid,
        ], absolute: false);

        $this->get($signedUrl)
            ->assertOk()
            ->assertSee('width: 1080px; height: 1920px;', false)
            ->assertSee('left: 120px; top: 160px; width: 360px; height: 180px;', false);
    }

    public function test_widget_actions_authorize_like_canvas_updates(): void
    {
        Http::fake([
            'https://widgets.example.test/embed' => Http::response('', 200),
        ]);

        $owner = User::factory()->withStreamerTeam()->create();
        $member = User::factory()->create();
        $canvas = Canvas::factory()->for($owner->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->create([
            'team_id' => $owner->currentTeam->id,
        ]);

        $owner->currentTeam->users()->attach($member, ['role' => TeamMemberRole::Moderator->value]);
        $member->switchTeam($owner->currentTeam);

        try {
            app(QuickCreateWidget::class)->create($member, $canvas, WidgetType::TaskList);
            $this->fail('Expected widget quick-create to be denied.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('canvas_widgets', 1);
        }

        try {
            app(CreateRemoteWidget::class)->create($member, $canvas, [
                'name' => 'Remote',
                'embed_url' => 'https://widgets.example.test/embed',
            ]);
            $this->fail('Expected remote widget creation to be denied.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('canvas_widgets', 1);
        }

        try {
            app(UpdateWidgetGeometry::class)->update($member, $widget, [
                'position_x' => 100,
                'position_y' => 100,
                'width' => 600,
                'height' => 400,
                'content_width' => 600,
                'content_height' => 400,
                'crop_top' => 0,
                'crop_right' => 0,
                'crop_bottom' => 0,
                'crop_left' => 0,
            ]);
            $this->fail('Expected widget geometry update to be denied.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $widget->fresh()->position_x);
        }

        try {
            app(DeleteWidgetInstance::class)->delete($member, $widget);
            $this->fail('Expected widget deletion to be denied.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('canvas_widgets', ['id' => $widget->id]);
        }

        try {
            app(RestoreWidgetInstanceState::class)->restore($member, $canvas, [[
                'id' => $widget->id,
                'position_x' => 100,
                'position_y' => 100,
                'width' => 600,
                'height' => 400,
                'content_width' => 600,
                'content_height' => 400,
                'crop_top' => 0,
                'crop_right' => 0,
                'crop_bottom' => 0,
                'crop_left' => 0,
                'is_visible' => true,
            ]]);
            $this->fail('Expected widget history restore to be denied.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $widget->fresh()->position_x);
        }
    }
}
