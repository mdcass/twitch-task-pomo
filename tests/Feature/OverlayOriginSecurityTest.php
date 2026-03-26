<?php

namespace Tests\Feature;

use App\Enums\Models\WidgetPreviewStatus;
use App\Livewire\Canvases\AddRemoteWidgetForm;
use App\Livewire\Canvases\CanvasComposer;
use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;
use App\Support\Routing\OriginUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OverlayOriginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_remote_widget_form_rejects_app_origin_urls(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test(AddRemoteWidgetForm::class, [
                'canvasId' => $canvas->id,
                'offcanvasId' => 'canvas-remote-widget-offcanvas',
            ])
            ->set('fields.embed_url', 'https://app.twitch-task-pomo.test/dashboard')
            ->call('submit')
            ->assertHasErrors(['fields.embed_url']);

        $this->assertDatabaseCount('canvas_widgets', 0);
    }

    public function test_remote_widget_form_rejects_loopback_ip_urls(): void
    {
        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test(AddRemoteWidgetForm::class, [
                'canvasId' => $canvas->id,
                'offcanvasId' => 'canvas-remote-widget-offcanvas',
            ])
            ->set('fields.embed_url', 'https://127.0.0.1/internal')
            ->call('submit')
            ->assertHasErrors(['fields.embed_url']);

        $this->assertDatabaseCount('canvas_widgets', 0);
    }

    public function test_canvas_composer_routes_remote_preview_through_the_overlay_origin(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $user = User::factory()->withStreamerTeam()->create();
        $canvas = Canvas::factory()->for($user->currentTeam)->create();
        $widget = WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
            'team_id' => $user->currentTeam->id,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
        ]);

        $component = Livewire::actingAs($user)
            ->test(CanvasComposer::class, ['canvasId' => $canvas->id]);

        $previewUrl = $component->instance()->previewUrlFor($widget->fresh());

        $this->assertIsString($previewUrl);
        $this->assertStringStartsWith('https://overlay.twitch-task-pomo.test/overlay/widgets/'.$widget->id, $previewUrl);
        $this->assertStringContainsString('signature=', $previewUrl);
        $this->assertStringContainsString('token=', $previewUrl);
    }

    public function test_signed_overlay_widget_route_allows_the_app_and_overlay_origins_to_frame_it(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $canvas = Canvas::factory()->create();
        $widget = WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
            'team_id' => $canvas->team_id,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
        ]);

        $url = app(OriginUrlGenerator::class)->temporarySignedOverlayRoute(
            'overlay.widgets.show',
            now()->addMinutes(10),
            ['widgetInstance' => $widget->id, 'token' => 'preview-token'],
        );

        $response = $this->get(str_replace((string) config('app.overlay_url'), '', $url));

        $response->assertOk()
            ->assertSee('widgets.example.test/embed')
            ->assertSee('background: transparent;', false)
            ->assertDontSee('background: #fff;', false)
            ->assertSee('allowtransparency="true"', false)
            ->assertHeader('Content-Security-Policy', "frame-ancestors https://app.twitch-task-pomo.test https://overlay.twitch-task-pomo.test");
    }

    public function test_signed_overlay_widget_route_renders_proprietary_widget_pages_without_editor_shell(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $canvas = Canvas::factory()->create([
            'name' => 'Published Scene',
        ]);

        $widget = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $canvas->team_id,
        ]);

        $url = app(OriginUrlGenerator::class)->signedOverlayRoute('overlay.widgets.show', [
            'widgetInstance' => $widget->id,
        ]);

        $response = $this->get(str_replace((string) config('app.overlay_url'), '', $url));

        $response->assertOk()
            ->assertSee('Deep Work Sprint')
            ->assertDontSee('data-shell-layout=', false)
            ->assertHeader('Content-Security-Policy', "frame-ancestors https://app.twitch-task-pomo.test https://overlay.twitch-task-pomo.test");
    }

    public function test_signed_overlay_canvas_route_renders_widget_frames_without_the_editor_shell(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $canvas = Canvas::factory()->create([
            'name' => 'Published Scene',
        ]);

        $widget = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
            'team_id' => $canvas->team_id,
        ]);

        $url = app(OriginUrlGenerator::class)->signedOverlayRoute('overlay.canvases.show', [
            'canvasUuid' => $canvas->uuid,
        ]);

        $response = $this->get(str_replace((string) config('app.overlay_url'), '', $url));

        $response->assertOk()
            ->assertSee('/overlay/widgets/'.$widget->id)
            ->assertSee('<iframe', false)
            ->assertSee('sandbox="allow-scripts"', false)
            ->assertSee('allowtransparency="true"', false)
            ->assertDontSee('data-shell-layout=', false)
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
    }

    public function test_signed_overlay_canvas_route_allows_same_origin_for_remote_widget_frames(): void
    {
        config()->set('app.url', 'https://app.twitch-task-pomo.test');
        config()->set('app.overlay_url', 'https://overlay.twitch-task-pomo.test');

        $canvas = Canvas::factory()->create([
            'name' => 'Published Scene',
        ]);

        $widget = WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
            'team_id' => $canvas->team_id,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
        ]);

        $url = app(OriginUrlGenerator::class)->signedOverlayRoute('overlay.canvases.show', [
            'canvasUuid' => $canvas->uuid,
        ]);

        $response = $this->get(str_replace((string) config('app.overlay_url'), '', $url));

        $response->assertOk()
            ->assertSee('/overlay/widgets/'.$widget->id)
            ->assertSee('sandbox="allow-scripts allow-same-origin"', false)
            ->assertSee('allowtransparency="true"', false)
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
    }
}
