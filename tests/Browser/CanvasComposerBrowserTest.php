<?php

use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;

it('keeps the add-widget dropdown open after adding a proprietary widget and keeps the overlay iframe selected for editing', function (): void {
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
        ->click('Create proprietary widget')
        ->wait(0.3)
        ->assertVisible('.offcanvas.show')
        ->press('Create and Attach Widget')
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

it('supports session-local undo and redo for non-destructive composer edits', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-history@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'History Scene',
    ]);

    $widget = WidgetInstance::factory()->for($canvas)->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'History Widget',
        'crop_top' => 24,
        'crop_right' => 36,
        'crop_bottom' => 48,
        'crop_left' => 60,
        'content_width' => 720,
        'content_height' => 480,
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-history@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('History Scene')
        ->click('[data-widget-layer-select="'.$widget->id.'"]')
        ->wait(0.5)
        ->assertScript(
            "(() => { const controller = document.querySelector('[data-composer-editor]')?.__canvasComposerController; const state = controller?.getHistoryState?.(); return !!state && state.undoCount === 0 && state.redoCount === 0 && state.canUndo === false && state.canRedo === false; })()",
            true,
        )
        ->click('[data-composer-reset="crop"]')
        ->wait(0.5)
        ->assertScript(
            "(() => document.body.textContent.includes('T: 0 / R: 0 / B: 0 / L: 0'))()",
            true,
        )
        ->assertScript(
            "(() => { const controller = document.querySelector('[data-composer-editor]')?.__canvasComposerController; const state = controller?.getHistoryState?.(); return !!state && state.undoCount === 1 && state.redoCount === 0 && state.canUndo === true && state.canRedo === false; })()",
            true,
        )
        ->assertScript(
            "(() => { document.querySelector('[data-composer-history=\"undo\"]')?.click(); return true; })()",
            true,
        )
        ->wait(0.5)
        ->assertScript(
            "(() => document.body.textContent.includes('T: 24 / R: 36 / B: 48 / L: 60'))()",
            true,
        )
        ->assertScript(
            "(() => { const controller = document.querySelector('[data-composer-editor]')?.__canvasComposerController; const state = controller?.getHistoryState?.(); return !!state && state.undoCount === 0 && state.redoCount === 1 && state.canUndo === false && state.canRedo === true; })()",
            true,
        )
        ->assertScript(
            "(() => { window.dispatchEvent(new KeyboardEvent('keydown', { key: 'y', ctrlKey: true, bubbles: true, cancelable: true })); return true; })()",
            true,
        )
        ->wait(0.5)
        ->assertScript(
            "(() => document.body.textContent.includes('T: 0 / R: 0 / B: 0 / L: 0'))()",
            true,
        )
        ->assertScript(
            "(() => { window.dispatchEvent(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true, cancelable: true })); return true; })()",
            true,
        )
        ->wait(0.5)
        ->assertScript(
            "(() => document.body.textContent.includes('T: 24 / R: 36 / B: 48 / L: 60'))()",
            true,
        )
        ->click('[data-composer-layer-action="visibility"][data-widget-id="'.$widget->id.'"]')
        ->wait(0.5)
        ->assertSee('Hidden')
        ->assertScript(
            "(() => { const controller = document.querySelector('[data-composer-editor]')?.__canvasComposerController; const state = controller?.getHistoryState?.(); return !!state && state.undoCount === 1 && state.redoCount === 0 && state.canUndo === true && state.canRedo === false; })()",
            true,
        );
});

it('routes widget deletion through the shared confirmation modal from inspector, layer actions, and keyboard shortcuts', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-delete@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Delete Scene',
    ]);

    $first = WidgetInstance::factory()->for($canvas)->taskList()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'First Layer',
        'z_index' => 0,
    ]);
    $second = WidgetInstance::factory()->for($canvas)->pomodoro()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Second Layer',
        'z_index' => 1,
    ]);
    $third = WidgetInstance::factory()->for($canvas)->remoteUrl('https://widgets.example.test/embed')->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Third Layer',
        'z_index' => 2,
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-delete@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Delete Scene')
        ->click('[data-widget-layer-select="'.$first->id.'"]')
        ->wait(0.5)
        ->click('[data-composer-selected-action="delete"][data-widget-id="'.$first->id.'"]')
        ->wait(0.5)
        ->assertVisible('.modal.show')
        ->assertSee('Delete this widget?')
        ->assertSee('Undo will not restore deleted widgets')
        ->click('[data-widget-delete-cancel]')
        ->wait(0.3)
        ->assertScript(
            "(() => document.querySelector('.modal.show') === null)()",
            true,
        )
        ->click('[data-composer-layer-action="delete"][data-widget-id="'.$second->id.'"]')
        ->wait(0.5)
        ->assertVisible('.modal.show')
        ->click('[data-widget-delete-confirm]')
        ->wait(0.8)
        ->assertScript(
            "(() => document.querySelector('[data-composer-editor]')?.dataset.selectedWidgetId === '".$third->id."')()",
            true,
        )
        ->assertScript(
            "(() => document.querySelector('[data-composer-layer-select=\"".$second->id."\"]') === null)()",
            true,
        )
        ->assertScript(
            "(() => { window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true })); return true; })()",
            true,
        )
        ->wait(0.5)
        ->assertVisible('.modal.show')
        ->assertSee('Third Layer')
        ->click('[data-widget-delete-cancel]');
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
        ->wait(0.5)
        ->assertScript(
            "(() => { const camera = document.querySelector('[data-composer-stage-camera]'); const stage = document.querySelector('[data-composer-stage]'); const badge = document.querySelector('[data-composer-fit-badge]'); if (!(camera instanceof HTMLElement) || !(stage instanceof HTMLElement) || !(badge instanceof HTMLElement)) { return false; } return stage.style.width === '1080px' && stage.style.height === '1920px' && stage.style.transform.startsWith('scale(') && stage.style.transform !== 'scale(1)' && parseFloat(camera.style.width || '0') > 0 && parseFloat(camera.style.width || '0') < 1080 && parseFloat(camera.style.height || '0') > parseFloat(camera.style.width || '0') && badge.textContent.includes('Fit ('); })()",
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

it('keeps widget placement consistent across viewport sizes', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-consistency@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Consistency Scene',
    ]);

    $widget = WidgetInstance::factory()->for($canvas)->taskList()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Task List',
        'position_x' => 160,
        'position_y' => 140,
        'width' => 520,
        'height' => 320,
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-consistency@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Consistency Scene')
        ->resize(1512, 982)
        ->wait(0.5)
        ->assertScript(
            "(() => { const camera = document.querySelector('[data-composer-stage-camera]'); const stage = document.querySelector('[data-composer-stage]'); const widget = document.querySelector('[data-widget-id=\"".$widget->id."\"]'); if (!(camera instanceof HTMLElement) || !(stage instanceof HTMLElement) || !(widget instanceof HTMLElement)) { return false; } window.__composerViewportBaseline = { left: widget.style.left, top: widget.style.top, width: widget.style.width, height: widget.style.height, cameraWidth: camera.style.width, cameraHeight: camera.style.height, transform: stage.style.transform }; return true; })()",
            true,
        )
        ->resize(1280, 800)
        ->wait(0.5)
        ->assertScript(
            "(() => { const baseline = window.__composerViewportBaseline; const camera = document.querySelector('[data-composer-stage-camera]'); const stage = document.querySelector('[data-composer-stage]'); const widget = document.querySelector('[data-widget-id=\"".$widget->id."\"]'); if (!baseline || !(camera instanceof HTMLElement) || !(stage instanceof HTMLElement) || !(widget instanceof HTMLElement)) { return false; } const geometryStable = widget.style.left === baseline.left && widget.style.top === baseline.top && widget.style.width === baseline.width && widget.style.height === baseline.height; const cameraChanged = camera.style.width !== baseline.cameraWidth || camera.style.height !== baseline.cameraHeight || stage.style.transform !== baseline.transform; return geometryStable && cameraChanged; })()",
            true,
        );
});

it('caps fit zoom at 100 percent when the viewport is larger than the canvas', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-fit-cap@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Small Scene',
        'width' => 320,
        'height' => 240,
    ]);

    WidgetInstance::factory()->for($canvas)->taskList()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Task List',
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-fit-cap@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Small Scene')
        ->resize(1600, 1200)
        ->wait(0.5)
        ->assertScript(
            "(() => { const camera = document.querySelector('[data-composer-stage-camera]'); const badge = document.querySelector('[data-composer-fit-badge]'); const stage = document.querySelector('[data-composer-stage]'); if (!(camera instanceof HTMLElement) || !(badge instanceof HTMLElement) || !(stage instanceof HTMLElement)) { return false; } return stage.style.width === '320px' && stage.style.height === '240px' && stage.style.transform === 'scale(1)' && camera.style.width === '320px' && camera.style.height === '240px' && badge.textContent.includes('Fit (100%)'); })()",
            true,
        );
});

it('keeps small viewports management-only while leaving layer actions available', function (): void {
    $user = User::factory()->withStreamerTeam()->create([
        'email' => 'browser-composer-small-viewport@example.test',
    ]);

    $canvas = Canvas::factory()->create([
        'team_id' => $user->currentTeam->id,
        'created_by_user_id' => $user->id,
        'name' => 'Compact Scene',
    ]);

    $widget = WidgetInstance::factory()->for($canvas)->taskList()->create([
        'team_id' => $user->currentTeam->id,
        'name' => 'Task List',
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-composer-small-viewport@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $this->visit('/canvases/'.$canvas->id.'/edit')
        ->assertSee('Compact Scene')
        ->resize(640, 900)
        ->click('[data-widget-layer-select="'.$widget->id.'"]')
        ->wait(0.5)
        ->assertScript(
            "(() => { const controller = document.querySelector('[data-composer-editor]')?.__canvasComposerController; return !!controller && controller.moveable === null; })()",
            true,
        )
        ->click('[data-composer-layer-action="visibility"][data-widget-id="'.$widget->id.'"]')
        ->wait(0.5)
        ->assertSee('Hidden');
});
