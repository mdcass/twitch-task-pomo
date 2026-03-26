# Manual Testing Guide

## Scope

- Review branch `feat/BP-10-widget-architecture`
- Validate the new proprietary widget architecture across `/widgets`, `/integrations`, canvas attachment flows, and signed overlay delivery.
- Confirm the split between reusable proprietary widgets and canvas-scoped remote embeds.
- Confirm lifecycle behavior for `draft`, `ready`, `pending connection`, and `archived`, including how publication interacts with those states.
- Treat this slice as a widget-system refactor, not a full Twitch runtime feature. The high-value checks are library management, provider-gated readiness, canvas placement, and secure overlay rendering.

## Initial states/setup

- Start from a normal local app setup with assets available. `composer setup` is sufficient for a first checkout, and `composer dev` is the expected runtime entrypoint.
- Use a verified user who owns the current team. The widget and integration pages are behind `auth` and `verified`, and all create/manage actions are owner-only.
- Prepare a second verified user on the same team with a non-owner role such as moderator so read-only team-member behavior can be checked.
- Prepare an outsider user on a different team for explicit access-denial checks.
- Seed or create at least one active canvas so widget attachment can be exercised from `/canvases/{id}/edit`.
- If you want to test real provider flows, configure local Twitch and Spotify OAuth credentials first. If provider OAuth is not configured locally, you can still fully review the blocked `pending connection` states and owner-only affordances.
- If you want to verify cross-origin framing and CSP behavior, set `APP_URL` and `APP_OVERLAY_URL` to different hosts locally. The security-sensitive overlay behavior is easiest to review with that split in place.
- Keep one browser session for the owner and one for the non-owner so permission differences are easy to compare.

## High-value files to review

- `app/Models/Widget.php`: reusable proprietary widget root, publication metadata, lifecycle state, UUID, usage counts, and activity logging surface.
- `app/Models/CanvasWidget.php`: canvas placement model for both proprietary and remote widgets, including geometry, crop, preview state, and runtime rendering helpers.
- `app/Policies/WidgetPolicy.php`: team members may view widgets, but only the team owner may create, update, publish, archive, restore, or reset them.
- `app/Actions/Widgets/CreateWidget.php`: widget creation defaults, schema versioning, default config and appearance, lifecycle resolution, and follower-goal state bootstrapping.
- `app/Actions/Widgets/UpdateWidget.php`: action-level validation, per-type normalization, and lifecycle recalculation after config changes.
- `app/Actions/Widgets/UpdateWidgetPublication.php` and `app/Actions/Widgets/SetWidgetArchivedState.php`: publish, regenerate, unpublish, archive, and restore semantics.
- `app/Support/Widgets/WidgetDefinitionRegistry.php` and `app/Support/Widgets/WidgetLifecycleResolver.php`: supported widget types and provider-driven readiness rules.
- `app/Support/Integrations/TeamProviderAuthResolver.php` and `app/Actions/Integrations/IntegrationConnectionService.php`: owner-owned provider resolution, OAuth workflow entrypoints, lifecycle refresh after connect or disconnect, and return-path handling.
- `app/Actions/CanvasWidgets/AttachWidgetToCanvas.php` and `app/Actions/CanvasWidgets/CreateRemoteCanvasWidget.php`: proprietary widget placement, remote embed validation, preview preflight, and editor defaults.
- `app/Http/Controllers/WidgetController.php` and `app/Http/Controllers/IntegrationController.php`: route-level page composition, filtering, publication URL generation, and owner/member capability split.
- `app/Livewire/Widgets/WidgetEditor.php`: shared widget edit surface, preview rendering, provider status, and owner-only actions.
- `app/Livewire/Canvases/AttachExistingWidgetForm.php` and `app/Livewire/Canvases/EditSharedWidgetForm.php`: canvas-side attach flow and shared-config offcanvas editing.
- `resources/views/widgets/index.blade.php`, `resources/views/widgets/show.blade.php`, and `resources/views/livewire/widgets/widget-editor.blade.php`: the main widget library and full-page editor UI.
- `resources/views/integrations/index.blade.php`, `resources/views/canvases/edit.blade.php`, and `resources/views/livewire/canvases/canvas-composer.blade.php`: owner/member integration UI, add-widget entrypoints, and canvas-side edit affordances.
- `database/migrations/2026_03_23_010003_create_widget_instances_table.php` and `database/migrations/2026_03_26_120000_create_follower_goal_states_table.php`: the new `widgets`, `canvas_widgets`, and follower-goal runtime state persistence.
- `tests/Feature/Widgets/WidgetRouteTest.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`, `tests/Feature/Widgets/WidgetDomainTest.php`, `tests/Feature/Integrations/IntegrationRouteTest.php`, `tests/Feature/CanvasComposerTest.php`, and `tests/Feature/OverlayOriginSecurityTest.php`: the most direct executable definition of expected behavior.

## Known limitations and intentional behaviour

- Provider connections are user-owned in v1 and resolved through the current team owner. Team members can inspect provider-backed widgets and integrations, but only the owner can connect, reconnect, disconnect, publish, archive, or repair them.
- Remote URL embeds remain canvas-scoped placements only. They should never appear in the top-level `/widgets` library.
- Provider-backed widgets may be created and attached while no provider is connected. They intentionally stay in `Pending Connection` and do not produce runtime output until the owner connects the required provider.
- Canvas-side shared widget editing is intentionally limited. Provider connect, repair, publish, archive, and other lifecycle controls stay on the full widget page rather than the canvas offcanvas.
- Archived widgets remain attached to canvases, but runtime and standalone rendering stay disabled until restored.
- Archiving preserves publication metadata. A previously published widget should become non-routable while archived, then resume on the same published URL after restore unless the key is later regenerated.
- Unpublishing clears `published_at` but intentionally keeps the existing `publication_key`. Republish should therefore reuse the same URL unless the user explicitly regenerates it.
- Remote embed creation only accepts HTTPS URLs and intentionally rejects app-origin URLs, overlay-origin URLs, loopback IPs, and private-network targets. Remote widgets that deny iframe embedding should persist as blocked previews rather than silently disappearing.
- The composer remains spatial-editing-first for desktop and tablet. Smaller screens keep the content visible but are not the primary editing target.
- Local widget preview tooling is intentionally reduced to `/local/widgets`, `/local/widgets/task-list`, and `/local/widgets/pomodoro` in `local` and `testing` environments. The older local Spotify preview seam was removed.

## Manual test scenarios

### 1. Widget library filters, create flow, and remote-widget exclusion

- Sign in as the team owner and open `/widgets`.
- Verify the page header describes reusable proprietary widgets for standalone URLs and canvas placement.
- Create a new Task List or Pomodoro widget from the inline create form.
- Verify the app redirects directly to `/widgets/{id}` and shows the widget editor rather than returning to the index.
- Return to `/widgets` and verify the new widget appears with type, lifecycle, canvas usage, and standalone status.
- Create or seed a remote canvas widget from a canvas page, then return to `/widgets` and verify the remote embed does not appear in the library.
- Use the search, type, and lifecycle filters together and verify matching widgets are returned while non-matching widgets disappear.
- Files to review: `app/Http/Controllers/WidgetController.php`, `app/Actions/Widgets/CreateWidget.php`, `resources/views/widgets/index.blade.php`, `tests/Feature/Widgets/WidgetRouteTest.php`.

### 2. Widget permissions: owner manage, member view-only, outsider blocked

- As the owner, open a widget detail page and confirm the page exposes save, publish, archive, and provider actions where applicable.
- As the non-owner team member, open the same widget detail page and verify the widget is visible but the page is effectively read-only.
- Confirm the member cannot see `Save changes`, `Publish`, `Archive`, `Restore`, `Connect Twitch`, `Connect Spotify`, or similar owner-only controls.
- As the outsider, attempt to open the same widget detail page and verify access is denied.
- If convenient, attempt a create request or owner-only mutation from the member session and verify it is forbidden.
- Files to review: `app/Policies/WidgetPolicy.php`, `app/Livewire/Widgets/WidgetEditor.php`, `resources/views/widgets/show.blade.php`, `resources/views/livewire/widgets/widget-editor.blade.php`, `tests/Feature/Widgets/WidgetRouteTest.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`.

### 3. Shared widget editing for Task List and Pomodoro types

- Create a Task List widget with a custom name.
- On the full widget page, change the title, pending items, completed items, and accent color, then save.
- Verify the live preview updates to match the edited values and the saved values persist after reload.
- Create or open a Pomodoro widget and verify its preview updates for title, timing, and appearance changes.
- Check that editing uses the shared widget definition, not a canvas-local copy.
- Files to review: `app/Actions/Widgets/UpdateWidget.php`, `app/Livewire/Widgets/WidgetEditor.php`, `resources/views/livewire/widgets/widget-editor.blade.php`, `resources/views/widgets/editor/task-list.blade.php`, `resources/views/widgets/editor/pomodoro.blade.php`, `tests/Feature/Widgets/WidgetDomainTest.php`.

### 4. Provider-backed widget lifecycle and owner integration actions

- Create a Follower Goal widget and a Spotify Now Playing widget while no matching provider connection exists for the team owner.
- Verify both widgets land in `Pending Connection`.
- On each widget page, verify the warning copy explains that owner action is required and that the widget may remain attached as a draft.
- As the owner, verify the widget page offers `Connect Twitch`, `Reconnect Twitch`, `Connect Spotify`, or `Reconnect Spotify` as appropriate.
- As the non-owner team member, verify the same widget page shows the lifecycle state but hides provider-connect actions.
- If local OAuth is configured, complete the connect flow from the widget or integrations page and verify the widget lifecycle changes from `Pending Connection` to `Ready`.
- Disconnect the provider from `/integrations` and verify the widget lifecycle returns to `Pending Connection`.
- Files to review: `app/Support/Widgets/WidgetLifecycleResolver.php`, `app/Support/Integrations/TeamProviderAuthResolver.php`, `app/Actions/Integrations/IntegrationConnectionService.php`, `resources/views/widgets/show.blade.php`, `resources/views/livewire/widgets/widget-editor.blade.php`, `resources/views/integrations/index.blade.php`, `tests/Feature/Integrations/IntegrationRouteTest.php`, `tests/Feature/Widgets/WidgetDomainTest.php`.

### 5. Integrations page owner/member split and dependent widget listing

- Open `/integrations` as the team owner.
- Verify Twitch and Spotify both render as separate provider cards, each showing connection status, usage count, and dependent widgets.
- If a provider is connected, verify the page shows owner connection identity details and a disconnect action.
- If a provider is not connected, verify the page clearly explains that dependent widgets stay blocked until the owner connects it.
- Open the same page as the non-owner team member and verify the status cards and dependent widgets remain visible, but connect, reconnect, and disconnect controls do not appear.
- Click through to a dependent widget from the integrations page and verify it opens the canonical widget page.
- Files to review: `app/Http/Controllers/IntegrationController.php`, `app/Actions/Integrations/IntegrationConnectionService.php`, `resources/views/integrations/index.blade.php`, `tests/Feature/Integrations/IntegrationRouteTest.php`.

### 6. Publication, key regeneration, unpublish, archive, and restore

- Open a ready widget as the owner and publish it.
- Verify the page shows a `Published URL` field and that opening the URL renders the widget without the authenticated app shell.
- Regenerate the URL and verify the previously copied URL stops working while the new URL succeeds.
- Unpublish the widget and verify the published URL no longer resolves.
- Republish the widget without regenerating and verify the URL works again at the same path.
- Archive a published widget and verify the widget page shows the archived lifecycle state while the previously valid standalone URL stops rendering.
- Restore the widget and verify the same standalone URL works again if the key was not regenerated.
- Files to review: `app/Actions/Widgets/UpdateWidgetPublication.php`, `app/Actions/Widgets/SetWidgetArchivedState.php`, `app/Http/Controllers/WidgetController.php`, `tests/Feature/Widgets/WidgetRouteTest.php`, `tests/Feature/Widgets/WidgetDomainTest.php`.

### 7. Follower Goal reset behavior

- Open a Follower Goal widget as the owner.
- If the widget has non-zero progress, verify the page shows the current count in preview/output.
- Use `Reset Goal Progress`.
- Verify the follower count resets to zero and any frozen state clears.
- As a non-owner team member, verify the reset action is not available.
- Files to review: `app/Actions/Widgets/UpdateFollowerGoalState.php`, `app/Livewire/Widgets/WidgetEditor.php`, `resources/views/livewire/widgets/widget-editor.blade.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`, `tests/Feature/Widgets/WidgetRouteTest.php`.

### 8. Canvas composer attach existing widget and edit shared settings

- Open `/canvases/{id}/edit` as the owner.
- Use `Add widget` and choose `Create proprietary widget` or `Attach existing widget`.
- For `Attach existing widget`, pick a ready shared widget and submit.
- Verify a new layer appears in the composer, the placement uses the proprietary widget label, and the widget page remains the canonical management surface.
- Select the placed widget and use `Edit shared settings`.
- For a Task List widget, verify the offcanvas loads the shared name, pending list, completed list, and appearance fields.
- Save changes and verify the canvas-side preview reflects the updated shared config.
- For a provider-backed widget, verify the offcanvas explicitly keeps provider connect, publish, and archive actions on the full widget page rather than exposing them inline.
- Files to review: `app/Actions/CanvasWidgets/AttachWidgetToCanvas.php`, `app/Livewire/Canvases/AttachExistingWidgetForm.php`, `app/Livewire/Canvases/EditSharedWidgetForm.php`, `resources/views/canvases/edit.blade.php`, `resources/views/livewire/canvases/canvas-composer.blade.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`.

### 9. Remote embed URL guardrails and preview blocking

- On a canvas edit page, choose `Add widget` then `Remote embed URL`.
- Attempt to add an `http://` URL and verify validation blocks it.
- Attempt to add a URL on the app origin or a loopback address and verify validation blocks it.
- Add a valid HTTPS remote embed that allows framing and verify it appears in the layer list as a canvas-scoped remote widget.
- If you have a test URL that returns restrictive iframe headers such as `X-Frame-Options: DENY`, add it and verify the widget persists with a blocked preview message rather than appearing as healthy.
- Verify remote widgets still do not appear in the `/widgets` library afterward.
- Files to review: `app/Actions/CanvasWidgets/CreateRemoteCanvasWidget.php`, `app/Support/Widgets/RemoteWidgetUrlGuard.php`, `app/Support/Widgets/RemoteWidgetPreviewInspector.php`, `tests/Feature/CanvasComposerTest.php`, `tests/Feature/OverlayOriginSecurityTest.php`.

### 10. Overlay-origin rendering and signed access

- Open a published standalone widget URL and verify the rendered page does not include the authenticated shell layout.
- If `APP_OVERLAY_URL` is split from `APP_URL`, inspect response headers and verify the framing policy matches the expected overlay route behavior.
- Open a signed canvas overlay URL and verify it renders iframe-backed widget frames rather than the editor shell.
- If the canvas contains a remote widget, verify its frame uses the same overlay route family and expected sandbox behavior.
- Modify the standalone widget URL so the path key is incorrect and verify the route returns not found.
- Verify an unpublished or archived widget no longer resolves even if you still have the old signed URL.
- Files to review: `routes/web.php`, `app/Http/Controllers/OverlayWidgetController.php`, `app/Http/Controllers/OverlayCanvasController.php`, `app/Support/Routing/OriginUrlGenerator.php`, `tests/Feature/Widgets/WidgetRouteTest.php`, `tests/Feature/OverlayOriginSecurityTest.php`.

## Recommended Supplemental Checks

- Run `php artisan test --parallel --filter=WidgetRouteTest` for route, authorization, and publication coverage.
- Run `php artisan test --parallel --filter=WidgetLivewireTest` for the widget editor and canvas-side offcanvas flows.
- Run `php artisan test --parallel --filter=WidgetDomainTest` for lifecycle, registry, publication, and archive semantics.
- Run `php artisan test --parallel --filter=IntegrationRouteTest` if you touched provider connection behavior.
- Run `php artisan test --parallel --filter=CanvasComposerTest` and `php artisan test --parallel --filter=OverlayOriginSecurityTest` for canvas placement and overlay-origin guardrails.
- Run `php artisan test --parallel --filter=AppShellLayoutTest` if the widgets or integrations pages look wrong in the shared shell.
- Use a real browser pass for the owner journey if anything in the composer or overlay behavior feels suspect. `composer test:browser` is the repo browser-test entrypoint.
- If you want extra confidence in the signed overlay behavior, inspect network headers and verify the CSP and framing behavior while loading published widget and canvas overlay URLs.
