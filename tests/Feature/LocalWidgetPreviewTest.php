<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\LocalToolingServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LocalWidgetPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_local_tooling_provider_supports_only_local_and_testing_environments(): void
    {
        $this->assertTrue(LocalToolingServiceProvider::supportsEnvironment('local'));
        $this->assertTrue(LocalToolingServiceProvider::supportsEnvironment('testing'));
        $this->assertFalse(LocalToolingServiceProvider::supportsEnvironment('production'));
    }

    public function test_launcher_requires_authentication(): void
    {
        $this->get(route('local.widgets.index', absolute: false))
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_launcher_renders_without_the_authenticated_app_shell(): void
    {
        $user = User::factory()->withStreamerTeam()->create();

        $this->actingAs($user)
            ->get(route('local.widgets.index', absolute: false))
            ->assertOk()
            ->assertSee('External Widget Preview Launcher')
            ->assertSee('Manage the now-playing widget source')
            ->assertSee('Generate a task widget URL')
            ->assertSee('Generate a pomodoro widget URL')
            ->assertDontSee('data-shell-layout=', false);
    }

    public function test_task_list_preview_renders_pending_and_completed_items_from_query_params(): void
    {
        $this->get(
            '/local/widgets/task-list?title=Focus%20Queue&pending[]=Plan%20stream%20outline&pending[]=Refine%20camera%20framing&completed[]=Warm%20up%20intro%20scene'
        )
            ->assertOk()
            ->assertSee('Focus Queue')
            ->assertSee('overlay-widget-card--task-list', false)
            ->assertSee('Plan stream outline')
            ->assertSee('Refine camera framing')
            ->assertSee('Warm up intro scene')
            ->assertSee('2 pending')
            ->assertSee('1 done')
            ->assertDontSee('data-shell-layout=', false);
    }

    public function test_focus_pomodoro_preview_seeds_a_live_countdown_from_the_end_time(): void
    {
        Carbon::setTestNow('2026-03-23 12:00:00');

        $endsAt = now()->addMinutes(20)->toIso8601String();
        $encodedEndsAt = rawurlencode($endsAt);

        $this->get("/local/widgets/pomodoro?title=Deep%20Work%20Sprint&state=focus&focus_minutes=25&break_minutes=5&ends_at={$encodedEndsAt}")
            ->assertOk()
            ->assertSee('Deep Work Sprint')
            ->assertSee('overlay-widget-card--pomodoro', false)
            ->assertSee('Focus Session')
            ->assertSee('20:00')
            ->assertSee('data-countdown-target="'.$endsAt.'"', false)
            ->assertSee('25 min')
            ->assertDontSee('data-shell-layout=', false);
    }

    public function test_break_pomodoro_preview_seeds_a_live_countdown_from_the_end_time(): void
    {
        Carbon::setTestNow('2026-03-23 12:00:00');

        $endsAt = now()->addMinutes(5)->toIso8601String();
        $encodedEndsAt = rawurlencode($endsAt);

        $this->get("/local/widgets/pomodoro?title=Reset%20Window&state=break&focus_minutes=25&break_minutes=5&ends_at={$encodedEndsAt}")
            ->assertOk()
            ->assertSee('Reset Window')
            ->assertSee('Break Window')
            ->assertSee('05:00')
            ->assertSee('data-countdown-target="'.$endsAt.'"', false);
    }

    public function test_paused_pomodoro_preview_renders_a_frozen_remaining_time(): void
    {
        $this->get('/local/widgets/pomodoro?title=Paused%20Timer&state=paused&focus_minutes=25&break_minutes=5&remaining_seconds=750')
            ->assertOk()
            ->assertSee('Paused Timer')
            ->assertSee('12:30')
            ->assertSee('Focus 25 min')
            ->assertSee('Break 5 min')
            ->assertDontSee('data-countdown-target=', false);
    }
}
