<?php

use App\Models\Canvas;
use App\Models\User;

it('creates a canvas and opens lifecycle modals from the browser UI', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-canvases@example.test',
    ]);

    $archivedCanvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Archived Browser Scene',
    ]);

    $archivedCanvas->delete();

    $this->visit('/login')
        ->fill('Email address', 'browser-canvases@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $page = $this->visit('/canvases')
        ->assertPathIs('/canvases')
        ->assertSee('Canvases')
        ->click('[data-canvas-add]')
        ->wait(0.3)
        ->assertVisible('.offcanvas.show')
        ->assertVisible('.offcanvas-backdrop.show')
        ->fill('#canvas-name', 'Browser Scene')
        ->fill('#canvas-width', '1600')
        ->fill('#canvas-height', '900')
        ->press('Create Canvas')
        ->wait(0.5);

    $canvas = Canvas::query()->where('name', 'Browser Scene')->firstOrFail();

    $page->assertPathIs('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Browser Scene');

    $page = $this->visit('/canvases')
        ->assertPathIs('/canvases')
        ->click('[data-canvas-action-toggle="'.$canvas->id.'"]')
        ->wait(0.2)
        ->click('[data-canvas-action="archive"]')
        ->wait(0.3)
        ->assertVisible('.jetstream-modal.show')
        ->assertVisible('.modal-backdrop.show')
        ->assertScript(
            "Number(getComputedStyle(document.querySelector('.jetstream-modal.show')).zIndex) > Number(getComputedStyle(document.querySelector('.modal-backdrop.show')).zIndex)",
            true,
        )
        ->assertSee('Archive Canvas')
        ->assertSee('Archive this canvas?')
        ->assertSee('Browser Scene');

    $page = $this->visit('/canvases')
        ->assertPathIs('/canvases')
        ->click('[data-canvas-filter="archived"]')
        ->wait(0.2)
        ->assertSee('Archived Browser Scene')
        ->click('[data-canvas-action-toggle="'.$archivedCanvas->id.'"]')
        ->wait(0.2)
        ->click('[data-canvas-action="restore"]')
        ->wait(0.3)
        ->assertVisible('.jetstream-modal.show')
        ->assertVisible('.modal-backdrop.show')
        ->assertScript(
            "Number(getComputedStyle(document.querySelector('.jetstream-modal.show')).zIndex) > Number(getComputedStyle(document.querySelector('.modal-backdrop.show')).zIndex)",
            true,
        )
        ->assertSee('Restore Canvas')
        ->assertSee('Restore this canvas?')
        ->assertSee('Archived Browser Scene');
});
