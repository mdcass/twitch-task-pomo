<?php

use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;

it('keeps the add-widget dropdown open after adding a built-in widget and keeps the overlay iframe selected for editing', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Browser Composer Scene',
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $page = $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Browser Composer Scene')
        ->assertSee('Layers')
        ->click('[data-widget-add]')
        ->wait(0.2)
        ->click('Built-in placeholder')
        ->wait(0.3)
        ->assertVisible('.offcanvas.show')
        ->press('Add Widget')
        ->wait(1.2)
        ->click('[data-widget-add]')
        ->wait(0.2)
        ->assertVisible('.dropdown-menu.show')
        ->wait(5.2)
        ->assertVisible('.dropdown-menu.show');

    $page->assertSee('Task List')
        ->click('[data-widget-layer-select]')
        ->wait(0.5)
        ->wait(1.0)
        ->assertScript("document.querySelector('[data-widget-id]') !== null", true)
        ->assertScript(
            "(() => !document.querySelector('.composer-widget__chrome'))()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const frame = root?.querySelector('iframe[data-widget-preview-mode=\"built_in\"]'); return !!frame && frame.getAttribute('src')?.startsWith('http://localhost/overlay/widgets/'); })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const controller = root?.__canvasComposerController; return !!controller && controller.moveable !== null && controller.currentTargetId !== null; })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const frame = root?.querySelector('iframe[data-widget-preview-mode=\"built_in\"]'); if (!frame) { return false; } window.__composerFrameReloads = 0; frame.addEventListener('load', () => { window.__composerFrameReloads += 1; }); return true; })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const componentId = root?.getAttribute('wire:id'); const selectedId = Number(root?.dataset.selectedWidgetId || 0); if (!componentId || !selectedId || !window.Livewire) { return false; } window.Livewire.find(componentId)?.\$call('saveGeometry', selectedId, { position_x: 160, position_y: 140, width: 360, height: 280, content_width: 720, content_height: 560, crop_top: 0, crop_right: 0, crop_bottom: 0, crop_left: 0 }); return true; })()",
            true,
        )
        ->wait(0.8)
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const controller = root?.__canvasComposerController; const target = controller?.moveable?.target; return !!controller && controller.currentTargetId !== null && target instanceof HTMLElement && target.isConnected && target.dataset.widgetId === controller.currentTargetId; })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const frame = root?.querySelector('iframe[data-widget-preview-mode=\"built_in\"]'); const src = frame?.getAttribute('src') || ''; return src !== '' && !src.includes('expires='); })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const scale = root?.querySelector('[data-widget-preview-scale]'); return !!scale && scale.style.transform !== '' && scale.style.transform !== 'scale(1, 1)'; })()",
            true,
        )
        ->assertScript(
            "(() => window.__composerFrameReloads === 0)()",
            true,
        );
});

it('highlights shortcut help text when crop, source, and stretch modifiers are active', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-shortcuts@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Shortcut Scene',
    ]);

    WidgetInstance::factory()->for($canvas)->taskList()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Task List',
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-shortcuts@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Shortcut Scene')
        ->click('[data-widget-layer-select]')
        ->wait(0.5)
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); root?.__canvasComposerController?.setModifierState({ alt: true, shift: false }); const cropHint = document.querySelector('[data-composer-shortcut=\"crop\"]'); const selected = document.querySelector('.composer-widget.is-selected'); return root?.dataset.cropModifierActive === 'true' && cropHint?.classList.contains('is-active') && cropHint?.classList.contains('is-crop-active') && selected?.classList.contains('is-crop-mode'); })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); root?.__canvasComposerController?.setModifierState({ alt: false, source: true, shift: false }); const sourceHint = document.querySelector('[data-composer-shortcut=\"source\"]'); return root?.dataset.sourceModifierActive === 'true' && sourceHint?.classList.contains('is-active') && sourceHint?.classList.contains('is-source-active'); })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); root?.__canvasComposerController?.setModifierState({ alt: false, source: false, shift: true }); const stretchHint = document.querySelector('[data-composer-shortcut=\"stretch\"]'); const cropHint = document.querySelector('[data-composer-shortcut=\"crop\"]'); const sourceHint = document.querySelector('[data-composer-shortcut=\"source\"]'); return root?.dataset.stretchModifierActive === 'true' && stretchHint?.classList.contains('is-active') && !cropHint?.classList.contains('is-crop-active') && !sourceHint?.classList.contains('is-source-active'); })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); root?.__canvasComposerController?.setModifierState({ alt: false, source: false, shift: false }); const resizeHint = document.querySelector('[data-composer-shortcut=\"resize\"]'); return resizeHint?.classList.contains('is-active'); })()",
            true,
        );
});

it('resets crop, source bounds, and aspect from the shortcut toolbar', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-resets@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Reset Scene',
    ]);

    WidgetInstance::factory()->for($canvas)->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Task List',
        'width' => 500,
        'height' => 500,
        'content_width' => 920,
        'content_height' => 640,
        'crop_top' => 24,
        'crop_right' => 36,
        'crop_bottom' => 48,
        'crop_left' => 60,
        'settings' => [
            'editor_defaults' => [
                'frame_width' => 640,
                'frame_height' => 360,
                'content_width' => 640,
                'content_height' => 360,
            ],
        ],
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-resets@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Reset Scene')
        ->click('[data-widget-layer-select]')
        ->wait(0.5)
        ->click('[data-composer-reset="crop"]')
        ->wait(0.5)
        ->assertScript(
            "(() => document.body.textContent.includes('T: 0 / R: 0 / B: 0 / L: 0'))()",
            true,
        )
        ->click('[data-composer-reset="source"]')
        ->wait(0.5)
        ->assertScript(
            "(() => { const contentTerms = Array.from(document.querySelectorAll('dt')); const contentTerm = contentTerms.find((term) => term.textContent.trim() === 'Content'); const text = contentTerm?.nextElementSibling?.textContent?.replace(/\\s+/g, ' ').trim() || ''; return text.includes('640 x 360'); })()",
            true,
        )
        ->click('[data-composer-reset="aspect"]')
        ->wait(0.5)
        ->assertScript(
            "(() => { const sizeTerms = Array.from(document.querySelectorAll('dt')); const sizeTerm = sizeTerms.find((term) => term.textContent.trim() === 'Size'); return sizeTerm?.nextElementSibling?.textContent.includes('500 x 281'); })()",
            true,
        );
});

it('shows advisory timeout UI for a timed out remote preview session', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-timeout@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Slow Preview Scene',
    ]);

    WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Remote Widget',
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-timeout@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Slow Preview Scene')
        ->assertSee('Remote Widget')
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const frame = root?.querySelector('[data-widget-preview-token]'); const controller = root?.__canvasComposerController; if (!frame || !controller) { return false; } controller.setPreviewSessionStatus(Number(frame.dataset.widgetId), frame.dataset.widgetPreviewToken, 'timeout'); return true; })()",
            true,
        )
        ->assertScript(
            "(() => { const message = document.querySelector('[data-widget-runtime-preview-message]'); const count = document.querySelectorAll('[data-widget-runtime-preview-message]').length; return !!message && count === 1 && message.textContent.includes('taking longer than expected'); })()",
            true,
        )
        ->assertScript(
            "(() => Array.from(document.querySelectorAll('dt')).some((term) => term.textContent.trim() === 'Session' && term.nextElementSibling?.textContent.includes('Slow')))()",
            true,
        );
});

it('fits portrait canvases into a bounded viewport', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-portrait@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Portrait Scene',
        'width' => 1080,
        'height' => 1920,
    ]);

    WidgetInstance::factory()->for($canvas)->taskList()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Task List',
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-portrait@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Portrait Scene')
        ->assertSee('Canvas 1080 x 1920')
        ->click('[data-widget-layer-select]')
        ->wait(0.5)
        ->assertScript(
            "(() => { const stage = document.querySelector('[data-composer-stage]'); const viewport = document.querySelector('[data-composer-viewport]'); if (!(stage instanceof HTMLElement) || !(viewport instanceof HTMLElement)) { return false; } const ratio = stage.clientWidth / Math.max(stage.clientHeight, 1); return Math.abs(ratio - (1080 / 1920)) < 0.02 && stage.clientWidth > 0 && stage.clientWidth < viewport.clientWidth && stage.clientHeight > stage.clientWidth; })()",
            true,
        )
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const componentId = root?.getAttribute('wire:id'); const selectedId = Number(root?.dataset.selectedWidgetId || 0); if (!componentId || !selectedId || !window.Livewire) { return false; } window.Livewire.find(componentId)?.\$call('saveGeometry', selectedId, { position_x: 120, position_y: 240, width: 360, height: 480, content_width: 720, content_height: 560, crop_top: 0, crop_right: 0, crop_bottom: 0, crop_left: 0 }); return true; })()",
            true,
        )
        ->wait(0.8)
        ->assertScript(
            "(() => { const root = document.querySelector('[data-composer-editor]'); const controller = root?.__canvasComposerController; const target = controller?.moveable?.target; return !!controller && controller.currentTargetId !== null && target instanceof HTMLElement && target.isConnected && target.dataset.widgetId === controller.currentTargetId; })()",
            true,
        );
});
