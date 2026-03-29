# Manual Testing Guide

## Scope

- Review commit `e9808db` (`feat: BP-10 Widget architecture`, 2026-03-26).
- Validate the new reusable proprietary widget architecture centered on `Widget` definitions plus canvas-scoped `WidgetInstance` placements.
- Cover the new authenticated app-shell surfaces at `/widgets`, `/widgets/{widget}/edit`, and `/integrations`, plus the canvas editor add-widget flows.
- Confirm the new standalone widget publishing flow and the signed overlay runtime routes for canvases and widget frames.
- Spot-check commit-adjacent regressions around shell navigation visibility, provider-avatar fallback, social OAuth debug handling, and stricter team/provider invariants.

## Initial states/setup

- Start from a normal local app setup with assets available. `composer dev` is the expected runtime entrypoint.
- Use a verified team owner account as the primary tester. Widget creation, widget mutation, publishing, and integration management are owner-only.
- Prepare a second verified user on the same team with a non-owner role such as moderator. This user is needed for view-only checks.
- Prepare an outsider user on a different team for explicit access-denial checks.
- Create or seed at least one canvas before testing the canvas editor flows.
- If you want to test Twitch or Spotify connection flows for real, configure local OAuth credentials first. If OAuth is not configured locally, you can still fully verify the pending/blocked states.
- If you want to inspect framing and overlay-origin behavior, set `APP_URL` and `APP_OVERLAY_URL` to different hosts locally.
- If you want to verify the social-auth debug override path, run the app in `local` environment. That override is intentionally ignored outside `local`.
- Keep separate browser sessions for owner, moderator, and outsider accounts so permission differences are obvious.
- For the Follower Goal reset scenario, seed a widget with non-zero `widget_follower_goal_states.current_count` before starting, because ordinary UI flows in this commit do not create follower events.

## High-value files to review

- `routes/web.php`: canonical route entrypoints, including `/widgets/{widget}/edit`, `/integrations`, published widget URLs, and signed overlay frame routes.
- `app/Models/Widget.php`: lifecycle resolution, publishing, archiving, restoring, standalone URL rotation, and Follower Goal reset behavior.
- `app/Models/WidgetInstance.php`: canonical canvas placement model, same-team invariants, preview helpers, and render geometry helpers.
- `app/Actions/Widgets/CreateWidget.php`: widget creation defaults, provider-gated initial lifecycle, and Follower Goal child-state bootstrapping.
- `app/Actions/Widgets/UpdateWidget.php`: editor-save validation and per-definition normalization.
- `app/Livewire/Widgets/WidgetIndex.php`: library filtering, owner-only creation, and redirect to the editor route.
- `app/Livewire/Widgets/WidgetEditor.php`: save, publish, unpublish, regenerate, archive, restore, provider-connect, and reset actions.
- `app/Services/Integrations/IntegrationConnectionService.php`: owner-only connect/disconnect workflow, return-path handling, provider-auth persistence, and widget lifecycle refresh.
- `app/Livewire/Integrations/IntegrationIndex.php`: owner/member behavior split and dependent-widget listing.
- `app/Actions/WidgetInstances/QuickCreateWidget.php` and `app/Actions/WidgetInstances/AttachWidgetToCanvas.php`: canvas quick-create and attach-existing flows.
- `app/Livewire/Canvases/EditWidgetSettingsForm.php`: canvas-side shared widget editing and the rule that provider/lifecycle actions stay on the full widget page.
- `app/Actions/WidgetInstances/CreateRemoteWidget.php` and `app/Support/Widgets/RemoteWidgetUrlGuard.php`: remote embed validation, preview inspection, and local/private/app-origin blocking.
- `app/Support/Shell/AppShellNavigation.php`: `Widgets` navigation visibility for team members, `Integrations` visibility for owners only, and provider-avatar fallback behavior.
- `app/Services/Auth/SocialAuthService.php`: social OAuth handshake persistence, local debug override handling, and existing-provider metadata refresh.
- `app/Models/Team.php` and `app/Policies/TeamPolicy.php`: current-team invariants, owner-backed provider resolution, and `manageIntegrations` authorization.
- `tests/Feature/Widgets/WidgetRouteTest.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`, `tests/Feature/Widgets/WidgetDomainTest.php`, `tests/Feature/Integrations/IntegrationRouteTest.php`, `tests/Feature/Integrations/IntegrationLivewireTest.php`, `tests/Feature/CanvasComposerTest.php`, `tests/Feature/OverlayOriginSecurityTest.php`, `tests/Feature/AppShellLayoutTest.php`, `tests/Feature/SocialAuthenticationTest.php`, and `tests/Feature/TeamSemanticsTest.php`: the most direct executable definitions of the expected behavior introduced by this commit.

## Known limitations and intentional behaviour

- The canonical proprietary widget UI is now `/widgets/{widget}/edit`. `/widgets/{id}` is intentionally gone and should return `404`.
- The widget library is proprietary-only. Remote embeds stay canvas-scoped and should never appear in `/widgets`.
- Team members may view widgets and the integrations page, but only the current team owner may create widgets, save widget edits, publish/unpublish/regenerate standalone URLs, archive/restore widgets, reset Follower Goal progress, or manage integrations.
- Provider-backed widgets can be created and attached before Twitch or Spotify is connected. They intentionally stay in `Needs Setup` and should not render on published runtime surfaces until the owner connects the required provider.
- Standalone URL state is independent from canvas placement. Turning the standalone URL off should not remove the widget from canvases.
- `archive()` clears `published_at` but keeps `publication_key`. `unpublish()` also keeps the key. Republish should reuse the same key unless the owner explicitly regenerates it.
- Canvas-side widget editing changes the shared widget definition everywhere that widget is used. Provider connect/repair, standalone URL controls, archive/restore, and other lifecycle actions intentionally stay on the full widget page.
- Remote embed URLs must be HTTPS and cannot point at the app origin, overlay origin, localhost-style hosts, loopback IPs, or private-network addresses.
- The shared shell should show `Widgets` to authenticated team members, but `Integrations` only to the current team owner.
- If a user lacks a profile photo, the shell intentionally falls back to the most recent usable provider avatar. Revoked or null-avatar provider auths should be ignored.
- `current_team_id` may be null in application code. Team deletion is intentionally blocked while any active user still points at that team as `current_team`.
- The social-auth `debug` override is intentionally local-only.

## Manual test scenarios

### 1. Shell navigation and page entrypoints

- Sign in as the team owner and confirm the shell shows both `Widgets` and `Integrations`.
- Sign in as a moderator on the same team and confirm the shell still shows `Widgets` but hides `Integrations`.
- Open `/widgets`, `/widgets/{widget}/edit`, and `/integrations` directly as the owner and confirm each page renders inside the normal app shell with the expected page header and breadcrumb band.
- Open `/widgets/{widget}/edit` as the moderator and confirm the page renders, but owner-only actions are absent.
- Attempt to open the same widget edit page as an outsider and confirm access is forbidden.
- Verify `/widgets/{id}` returns `404` and is no longer a valid page.
- If you have a linked provider auth with an avatar and no profile photo, confirm the shell avatar uses the provider image.
- Files to review: `routes/web.php`, `app/Http/Controllers/WidgetController.php`, `app/Http/Controllers/IntegrationController.php`, `app/Support/Shell/AppShellNavigation.php`, `tests/Feature/AppShellLayoutTest.php`, `tests/Feature/Widgets/WidgetRouteTest.php`.

### 2. Widget library create/filter flow and remote-widget exclusion

- Open `/widgets` as the owner.
- Create a Task List widget and verify the app redirects to `/widgets/{widget}/edit`, not back to the index.
- Return to `/widgets` and confirm the new widget appears with its type, health, canvas usage count, standalone URL status, and schema version.
- Create a provider-backed widget such as Follower Goal or Spotify Now Playing and confirm it appears with `Needs Setup` when no provider is connected.
- Add a remote widget from a canvas page, then return to `/widgets` and confirm that remote widget never appears in the library.
- Use the search, type, and health filters together and confirm only matching proprietary widgets remain visible.
- Files to review: `app/Livewire/Widgets/WidgetIndex.php`, `app/Actions/Widgets/CreateWidget.php`, `resources/views/widgets/index.blade.php`, `resources/views/livewire/widgets/widget-index.blade.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`, `tests/Feature/Widgets/WidgetRouteTest.php`.

### 3. Widget editor save flow and owner/member split

- Open a Task List or Pomodoro widget as the owner.
- Change the widget name plus a few type-specific config and appearance fields, save, then reload the page.
- Confirm the saved values persist and the preview reflects the updated config.
- Open the same widget page as the moderator and confirm the widget is visible but `Save changes`, provider-connect buttons, standalone URL buttons, and archive/restore actions are absent.
- For a Follower Goal widget without Twitch connected, confirm the page shows the owner-action-required warning and the provider status block.
- Files to review: `app/Livewire/Widgets/WidgetEditor.php`, `app/Actions/Widgets/UpdateWidget.php`, `resources/views/widgets/edit.blade.php`, `resources/views/livewire/widgets/widget-editor.blade.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`, `tests/Feature/Widgets/WidgetDomainTest.php`.

### 4. Integrations page and provider-backed lifecycle refresh

- Create a Follower Goal widget and a Spotify Now Playing widget with no provider connections present.
- Open `/integrations` as the owner and confirm Twitch and Spotify both render as provider cards with usage counts and dependent widgets.
- Confirm the owner sees `Connect` or `Reconnect` controls, while the moderator sees the same status information but no connect/disconnect controls.
- Open the dependent widget from `/integrations` and confirm it links to the canonical widget edit page.
- If local OAuth is configured, complete a provider connection from `/integrations` or the widget page and confirm the affected widgets move from `Needs Setup` to `Ready`.
- Disconnect that provider from `/integrations` and confirm the affected widgets return to `Needs Setup`.
- Files to review: `app/Services/Integrations/IntegrationConnectionService.php`, `app/Http/Controllers/IntegrationConnectionController.php`, `app/Livewire/Integrations/IntegrationIndex.php`, `resources/views/integrations/index.blade.php`, `resources/views/livewire/integrations/integration-index.blade.php`, `tests/Feature/Integrations/IntegrationRouteTest.php`, `tests/Feature/Integrations/IntegrationLivewireTest.php`, `tests/Feature/Widgets/WidgetDomainTest.php`.

### 5. Standalone URL publish, regenerate, unpublish, archive, and restore

- Open a ready Task List or Pomodoro widget as the owner.
- Turn the standalone URL on and confirm a read-only URL field appears.
- Open that URL and confirm it renders the widget itself without the authenticated editor shell.
- Regenerate the standalone URL and confirm the old URL stops working while the new one succeeds.
- Turn the standalone URL off and confirm the URL stops resolving.
- Turn it back on without regenerating and confirm the key is reused.
- Archive the widget and confirm the standalone URL stops resolving even though the widget may still be attached to canvases.
- Restore the widget and confirm the lifecycle returns from `Archived`, while the widget remains unpublished until explicitly turned on again.
- Files to review: `app/Models/Widget.php`, `app/Livewire/Widgets/WidgetEditor.php`, `app/Http/Controllers/OverlayWidgetController.php`, `routes/web.php`, `tests/Feature/Widgets/WidgetDomainTest.php`, `tests/Feature/Widgets/WidgetRouteTest.php`.

### 6. Follower Goal progress preservation and reset

- Seed a Follower Goal widget with non-zero progress before starting this scenario.
- Open the widget edit page as the owner and note the current progress shown in the preview/output.
- Save ordinary config changes such as title, goal target, or appearance, then confirm the progress value is preserved.
- Use `Reset Goal Progress` and confirm the count resets to zero and any frozen state clears.
- Open the same widget as the moderator and confirm the reset action is not available.
- Files to review: `app/Models/Widget.php`, `app/Models/Widgets/FollowerGoalState.php`, `app/Livewire/Widgets/WidgetEditor.php`, `tests/Feature/Widgets/WidgetDomainTest.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`, `tests/Feature/Widgets/WidgetRouteTest.php`.

### 7. Canvas quick-create, attach-existing, and shared widget settings

- Open `/canvases/{canvas}/edit` as the owner.
- Use `Add widget` -> `Quick-create widget` and create a Task List or Pomodoro widget. Confirm a new layer appears immediately on the canvas.
- Use `Add widget` -> `Attach widget` and attach an existing proprietary widget. Confirm the new layer appears and uses the shared widget name/type.
- Select an attached proprietary widget and use `Edit widget settings`.
- Confirm the offcanvas clearly states the edits apply anywhere the widget is used.
- Save changes in the offcanvas and confirm the canvas preview updates to the shared widget config.
- For a provider-backed widget, confirm the offcanvas warns that connect/repair/standalone/archive actions stay on the full widget page.
- Use `Open widget page` from the composer and confirm it opens the canonical editor route.
- Files to review: `app/Actions/WidgetInstances/QuickCreateWidget.php`, `app/Actions/WidgetInstances/AttachWidgetToCanvas.php`, `app/Livewire/Canvases/QuickCreateWidgetForm.php`, `app/Livewire/Canvases/AttachExistingWidgetForm.php`, `app/Livewire/Canvases/EditWidgetSettingsForm.php`, `resources/views/canvases/edit.blade.php`, `resources/views/livewire/canvases/canvas-composer.blade.php`, `tests/Feature/CanvasComposerTest.php`, `tests/Feature/Widgets/WidgetLivewireTest.php`.

### 8. Remote widget validation, preview state, and overlay routing

- From the canvas editor, use `Add widget` -> `Add remote widget`.
- Attempt to save an `http://` URL and confirm validation rejects it as non-HTTPS.
- Attempt to save a URL on the app origin, overlay origin, `localhost`, or a loopback/private IP and confirm validation rejects it.
- Save a valid HTTPS remote widget and confirm it creates a canvas layer but does not appear in `/widgets`.
- If you have a test URL that blocks iframe embedding, save it and confirm the widget persists with a blocked or unavailable preview state instead of silently disappearing.
- Inspect the canvas overlay output and confirm remote widgets render through the overlay-origin widget-frame route, not the authenticated editor shell.
- Files to review: `app/Livewire/Canvases/AddRemoteWidgetForm.php`, `app/Actions/WidgetInstances/CreateRemoteWidget.php`, `app/Support/Widgets/RemoteWidgetUrlGuard.php`, `app/Http/Controllers/OverlayWidgetController.php`, `resources/views/overlay/canvas.blade.php`, `tests/Feature/CanvasComposerTest.php`, `tests/Feature/OverlayOriginSecurityTest.php`.

### 9. Signed overlay runtime behavior for canvases and published widgets

- Open a published standalone widget URL and confirm it renders only the widget output.
- Open a signed canvas overlay URL and confirm the page renders the configured canvas dimensions and iframe-backed widget frames without the editor shell.
- Confirm ready proprietary widgets render, while proprietary widgets in `Needs Setup`, `Broken`, or `Archived` do not appear in runtime output.
- If the canvas contains a remote widget, inspect the iframe and confirm it uses the relaxed remote sandbox (`allow-scripts allow-same-origin`) while proprietary widgets use the stricter proprietary frame route.
- Modify the signed or keyed URL so it is invalid and confirm the route no longer resolves.
- Files to review: `app/Http/Controllers/OverlayCanvasController.php`, `app/Http/Controllers/OverlayWidgetController.php`, `resources/views/overlay/canvas.blade.php`, `routes/web.php`, `tests/Feature/OverlayOriginSecurityTest.php`, `tests/Feature/Widgets/WidgetRouteTest.php`, `tests/Feature/CanvasComposerTest.php`.

### 10. Social-auth and team-invariant regression spot checks

- In `local` environment, start a register flow with a supported provider and `debug=no_email`, then confirm the handshake persists and routes the user into the social-email onboarding path instead of silently completing registration.
- Repeat the same flow outside `local` and confirm the debug override is ignored.
- If you have an existing linked provider account, sign in through that provider and confirm the callback completes login rather than forcing a new registration path.
- Review any team-management or destructive flows you touch locally and confirm no active user can leave a team deleted while still pointing at it as `current_team`.
- Files to review: `app/Services/Auth/SocialAuthService.php`, `app/Actions/Auth/CompleteSocialRegistration.php`, `app/Models/Team.php`, `app/Models/User.php`, `tests/Feature/SocialAuthenticationTest.php`, `tests/Feature/TeamSemanticsTest.php`.

## Recommended Supplemental Checks

- Run `php artisan test --parallel --filter=WidgetRouteTest`.
- Run `php artisan test --parallel --filter=WidgetLivewireTest`.
- Run `php artisan test --parallel --filter=WidgetDomainTest`.
- Run `php artisan test --parallel --filter=IntegrationRouteTest`.
- Run `php artisan test --parallel --filter=IntegrationLivewireTest`.
- Run `php artisan test --parallel --filter=CanvasComposerTest`.
- Run `php artisan test --parallel --filter=OverlayOriginSecurityTest`.
- Run `php artisan test --parallel --filter=AppShellLayoutTest`.
- Run `php artisan test --parallel --filter=SocialAuthenticationTest`.
- Run `php artisan test --parallel --filter=TeamSemanticsTest`.
- Use `composer test:browser` if you want extra confidence in the canvas editor interactions, shell navigation, or overlay rendering in a real browser.
