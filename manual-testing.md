# Manual Testing Guide

## Scope

- Review commit `09182040258ce79c5a6d517d94da8731a8173152` (`feat: BP-8 canvas CRUD`).
- Validate the new `/canvases` experience: team-scoped list and edit pages, create/edit/archive/restore flows, and the shared modal/offcanvas overlay plumbing used by those flows.
- Confirm the Phoenix shell changes used by canvas pages, especially the content-top breadcrumb band and shared overlay behavior.
- Treat the canvas workspace as intentionally incomplete in this slice. The commit establishes metadata CRUD and page structure, not live widget composition.

## Initial states/setup

- Start the app in a normal local environment with assets available. `composer setup` is enough for a first-time checkout, and `composer dev` is the normal runtime entrypoint.
- Use a verified user who owns the current team. The `/canvases` pages are behind `auth` and `verified`.
- Prepare a second verified user who belongs to the same team but is not the owner so read-only team-member behavior can be checked.
- Ensure the owner account has at least one active canvas and one archived canvas.
- If the team starts empty, create one canvas first, then archive it from the owner account to seed the archived state.
- Keep one browser session for the owner account and one for the non-owner account so authorization differences are easy to compare.

## High-value files to review

- `app/Policies/CanvasPolicy.php`: defines the main boundary for canvas access. Team members can view, but only team owners can create, update, archive, and restore.
- `app/Actions/Canvases/CreateCanvas.php`: owner-only create path, transaction boundary, and `canvas.created` activity logging.
- `app/Actions/Canvases/UpdateCanvas.php`: metadata validation, dirty-check behavior, and `canvas.updated` activity logging.
- `app/Actions/Canvases/ArchiveCanvas.php` and `app/Actions/Canvases/RestoreCanvas.php`: soft-delete lifecycle and audit events for archive/restore.
- `app/Actions/Canvases/Concerns/ValidatesCanvasAttributes.php`: required field and positive integer validation for name, width, and height.
- `app/Livewire/Canvases/CanvasIndex.php`: list filtering, recent-canvas selection, and the dispatch points that open edit offcanvas and lifecycle modal hosts.
- `app/Livewire/Canvases/CanvasForm.php`: create vs edit behavior, redirect after create, and in-place save after edit.
- `app/Livewire/Canvases/CanvasLifecycleModal.php`: archive/restore confirm behavior and redirect-or-close handling.
- `app/Livewire/Offcanvas.php`, `resources/views/components/offcanvas.blade.php`, and `resources/js/phoenix/app.js`: shared offcanvas lifecycle, payload merging, Bootstrap-driven show/hide behavior, and focus return handling.
- `resources/views/canvases/index.blade.php` and `resources/views/livewire/canvases/canvas-index.blade.php`: page composition, filters, recent-canvas hero, and row/action rendering.
- `resources/views/canvases/edit.blade.php`: metadata card, owner-only actions, and the intentionally placeholder workspace panel.
- `resources/views/livewire/canvases/canvas-form.blade.php` and `resources/views/livewire/canvases/canvas-lifecycle-modal.blade.php`: field-level validation UI and destructive confirmation copy.
- `database/migrations/2026_03_23_020000_add_deleted_at_to_canvases_table.php`: schema support for soft-deleted canvases and team/deleted index coverage.
- `tests/Feature/CanvasCrudTest.php`, `tests/Browser/CanvasCrudBrowserTest.php`, and `tests/Feature/OffcanvasComponentTest.php`: the most direct executable description of intended behavior.

## Known limitations and intentional behaviour

- The canvas editor is metadata-only in this slice. The workspace card is a placeholder and does not support adding, moving, resizing, or configuring widgets yet.
- Archived canvases are intentionally removed from the active edit route. A direct visit to `/canvases/{id}/edit` for an archived canvas should fail rather than reopen it.
- Archived rows in the canvas library are intentionally not clickable. Restore should happen from the row action menu, not by opening the edit page.
- Create uses the shared offcanvas host and then redirects to the edit page. Edit uses the shared offcanvas host but saves in place and closes the panel.
- Only team owners should see mutation affordances such as `Add canvas`, `Edit`, `Archive`, and `Restore`. Non-owner team members can still view active canvases for the current team.
- The `Recent canvases` hero intentionally shows at most three active canvases ordered by most recent `updated_at`. Archived canvases do not appear there.
- This commit adds audit events for create, update, archive, and restore, but those events are not surfaced in a dedicated UI yet.

## Manual test scenarios

### 1. Owner creates a new canvas from the index page

- Sign in as the team owner and open `/canvases`.
- Verify the page shows the `Canvases` heading, the breadcrumb band above the header, and an `Add canvas` action.
- Open the create offcanvas from both the header button and the add-card in `Recent canvases`.
- Verify the panel opens from the side, focus lands in the name field, and the form defaults to `1920 x 1080`.
- Create a canvas with a distinct name and non-default dimensions such as `1600 x 900`.
- Verify the app redirects to `/canvases/{id}/edit`, the new name appears in the page header, and the metadata card shows the saved dimensions.
- Files to review: `resources/views/canvases/index.blade.php`, `resources/views/livewire/canvases/canvas-index.blade.php`, `app/Livewire/Canvases/CanvasForm.php`, `resources/views/livewire/canvases/canvas-form.blade.php`, `app/Actions/Canvases/CreateCanvas.php`.

### 2. Validation blocks invalid canvas metadata

- As the owner, reopen the create offcanvas or the edit offcanvas for an existing active canvas.
- Submit the form with an empty name.
- Submit the form with `0`, a negative number, or a blank value for width and height.
- Verify inline validation errors render next to the invalid fields and no canvas mutation is applied.
- Verify cancel closes the offcanvas without changing data.
- Files to review: `app/Actions/Canvases/Concerns/ValidatesCanvasAttributes.php`, `app/Livewire/Canvases/CanvasForm.php`, `resources/views/livewire/canvases/canvas-form.blade.php`.

### 3. Owner updates canvas metadata in place

- Open an existing active canvas edit page as the owner.
- Confirm the metadata card shows the current name and dimensions, and the workspace card still reads as a placeholder.
- Open the `Edit` offcanvas from the metadata card and change the name and dimensions.
- Save the form.
- Verify the offcanvas closes, the page remains on the same edit URL, and the updated metadata is visible immediately.
- Return to `/canvases` and verify the same canvas appears with the new values in the library and is promoted in `Recent canvases` if it is among the three most recently updated active canvases.
- Files to review: `resources/views/canvases/edit.blade.php`, `app/Livewire/Canvases/CanvasForm.php`, `app/Actions/Canvases/UpdateCanvas.php`, `app/Livewire/Canvases/CanvasIndex.php`.

### 4. Archive and restore use soft-delete lifecycle states

- As the owner, archive an active canvas from the edit-page action menu.
- Verify the archive confirmation modal opens, includes the canvas name and dimensions, and redirects back to `/canvases` after confirmation.
- On `/canvases`, verify the archived canvas is absent from the default `Active` filter.
- Switch to `Archived` and verify the canvas appears with an `Archived` badge, is not row-clickable, and offers a `Restore` action instead of `Edit` or `Archive`.
- Restore the canvas from the row action menu.
- Verify it disappears from `Archived`, reappears in `Active`, and can be opened on the edit page again.
- Files to review: `app/Livewire/Canvases/CanvasLifecycleModal.php`, `resources/views/livewire/canvases/canvas-lifecycle-modal.blade.php`, `app/Actions/Canvases/ArchiveCanvas.php`, `app/Actions/Canvases/RestoreCanvas.php`, `database/migrations/2026_03_23_020000_add_deleted_at_to_canvases_table.php`.

### 5. Team members are read-only

- Sign in as a non-owner who belongs to the same team as the canvas owner.
- Open `/canvases` and verify the page is accessible.
- Open an active canvas edit page and verify the canvas can be viewed.
- Confirm mutation controls are absent: no `Add canvas` action, no edit offcanvas trigger, and no archive/restore actions.
- Attempt to visit the edit route for an archived canvas directly and verify it fails rather than exposing archived content.
- Files to review: `app/Policies/CanvasPolicy.php`, `app/Http/Controllers/CanvasController.php`, `app/Livewire/Canvases/CanvasIndex.php`, `resources/views/canvases/edit.blade.php`.

### 6. Canvas filters, hero cards, and content-top shell behave consistently

- As the owner, create or update enough canvases to have more than three active canvases plus one archived canvas.
- Verify `Recent canvases` shows only the three most recently updated active canvases.
- Verify the library filter buttons switch between `Active`, `All`, and `Archived` without exposing the wrong set.
- Verify active rows navigate to the edit page when clicked, while archived rows do not navigate on row click.
- Confirm both the index page and edit page render a single breadcrumb trail in the sticky content-top band and do not duplicate breadcrumb markup in the main header.
- Files to review: `app/Livewire/Canvases/CanvasIndex.php`, `resources/views/livewire/canvases/canvas-index.blade.php`, `resources/views/canvases/index.blade.php`, `resources/views/canvases/edit.blade.php`.

### 7. Shared overlay plumbing feels correct in the browser

- Open and close the create offcanvas, edit offcanvas, and archive/restore modal multiple times from real page actions.
- Verify the correct title is shown for each overlay and the right record payload is loaded each time.
- Verify backdrop, dismissal, and focus return feel correct when closing with the close affordance, Escape, and successful submit where applicable.
- Pay special attention to reopening the same overlay for different records so stale payload data is not reused.
- Files to review: `app/Livewire/Offcanvas.php`, `resources/views/components/offcanvas.blade.php`, `resources/js/phoenix/app.js`, `app/Livewire/Canvases/CanvasIndex.php`.

## Recommended Supplemental Checks

- Run `php artisan test --parallel --filter=CanvasCrudTest` for the main feature coverage around authorization, filters, and lifecycle transitions.
- Run `php artisan test --parallel --filter=OffcanvasComponentTest` to validate the shared offcanvas host contract and payload behavior.
- Run `php artisan test --parallel --filter=AppShellLayoutTest` if the breadcrumb band or shell layout looks off during manual review.
- Run `composer test:browser` or `./vendor/bin/pest --configuration=phpunit.browser.xml tests/Browser/CanvasCrudBrowserTest.php` for a real browser pass over the owner journey.
- If you want extra confidence in audit behavior, inspect the `activity_log` table after create, update, archive, and restore actions to confirm the expected `canvas.*` events were written.
- Use browser devtools responsive mode while exercising the overlays and canvas pages. The new content-top band and offcanvas interactions are visual enough that a quick desktop and narrow-width pass is worthwhile.
