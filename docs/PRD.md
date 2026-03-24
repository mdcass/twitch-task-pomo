# Product Requirements Document

## Project

Stream Overlay Co-Working Toolkit

## Author

Mike Casson

## Version

0.8

## 1. Product Overview

This project is a commercial streaming overlay platform focused initially on the co-working and productivity streaming niche, especially Twitch creators running Pomodoro and task-list driven streams.

The near-term product differentiator is a single composited browser-source experience for OBS and similar streaming tools. The product name for this capability is `Overlay Composer`.

`Overlay Composer` allows a streamer to configure multiple overlay elements inside the application while exposing only one browser source URL to OBS. This reduces setup complexity, gives the product a clearer user-facing story, and should be treated as one of the earliest meaningful releases once the architectural and UI foundation is stable enough.

The PRD should remain Twitch-first for streamer-facing requirements while allowing local application auth and selected non-Twitch identity providers for general account access. YouTube-specific requirements are intentionally out of scope at this stage.

## 2. Product Direction

The initial product should take inspiration from the operational simplicity of Super Sweet Bot and related Twitch productivity overlays: quick setup, command-driven task updates, and a clean browser-source workflow.

The initial external offer should center on `Overlay Composer` rather than a fully mature theming system. Public beta should happen before advanced theming is perfected.

The planned free tier for the early canvas offer is up to 40 hours of streaming per billing cycle. Metering should be based on Twitch-authenticated streamer start and stop events, with Twitch API verification used to resolve missed, duplicated, or ambiguous session notifications.

## 3. Goals

- Deliver polished productivity overlays for Twitch-focused co-working streamers.
- Release a secure single-browser-source canvas early.
- Support Twitch chat bot control for core task and Pomodoro flows.
- Give viewers a way to stay synchronized with the streamer's Pomodoro session.
- Keep the Laravel application architecture team-oriented and auditable from the start.
- Avoid unnecessary frontend complexity and introduce extra JavaScript libraries only when the requirement clearly justifies them.

## 4. Non-Goals for Early Phases

- A dedicated `Session status` widget is not required and is removed from scope.
- A dedicated `Next task` or `Now task` on-screen indicator is not required for MVP and may be reconsidered later.
- Advanced theme packs are not required before public beta.
- YouTube user support is not part of the current PRD.

## 5. Target Users

Primary users are Twitch productivity streamers, co-working stream hosts, and educational streamers who want low-friction overlay setup.

Secondary users are moderators and team collaborators who need permissioned control over overlays and chat-driven workflows.

Additional audience users are viewers who want to stay aligned with the streamer's focus session while muting the stream or listening to their own music.

## 6. Core Product Areas

### 6.1 Overlay Widgets

Initial widgets:

- Pomodoro timer
- Task list

Initial widget requirements:

- Positioning
- Resizing
- Visibility toggles
- Composer compatibility
- Theme hooks for later visual expansion

The task-list experience should be designed so it can evolve toward backlog and focus flows inspired by Super Sweet Bot without requiring a separate noisy indicator widget in the first release.

Pomodoro defaults and task behavior:

- Default Pomodoro timing should start from a 25 minute focus and 5 minute break baseline
- Streamers should be able to start custom sessions via chat commands such as `!pomo 50/5`
- A direct `!break` command should be supported
- Pomodoro timing should be configurable primarily through the `!pomo` command flow
- Pomodoro state should auto-advance, with commands such as `!pomo extend` available to prolong the current state
- Viewer-submitted task data should be archived for later analytics rather than discarded
- Task visibility and active work state remain scoped to a single stream session rather than carrying across streams

Task list presentation and limits:

- There should be no hard per-viewer task maximum by default, though streamer-configurable policies may impose limits
- The overlay should display up to 20 tasks at one time
- Streamer policies may additionally cap how many tasks from an individual viewer are visibly active at once to reduce visual noise
- Overflow should scroll rather than paginate or truncate
- Tasks should be grouped by status and then preserve insertion order within that grouping
- The submitting viewer's Twitch username should be displayed alongside the task

### 6.2 Overlay Composer

`Overlay Composer` is a configurable scene rendered as a single browser source.

Capabilities:

- Compose multiple widgets into one scene
- Position and resize widgets
- Persist layout per team and canvas
- Render a production-ready OBS browser source

Output example:

`https://app.example/overlay/{canvas_uuid}`

Security requirements:

- `canvas_id` should be a UUID
- Overlay access should use Laravel signed URLs
- Signed URLs should be appropriate for OBS usage and regeneration workflows

Composer defaults:

- The configured canvas dimensions should be the source of truth for editor layout and overlay rendering, and the editor preview should match the canvas aspect ratio and usable area
- The initial composer viewport should fit the configured canvas inside a bounded workspace area; richer viewport controls such as zoom and pan can follow in a later iteration if needed
- Teams should be able to create multiple canvases with no initial hard limit
- The primary Phase 1 editing experience should be live drag-and-drop, with form-based position and size fallback for browsers that struggle with richer interaction
- Widgets should be able to overlap and streamers should control stacking order
- If the editor uses a simplified wireframe view, overlapping widget boxes should be visually flagged with a red border
- The dashboard should include a preview mode before a streamer goes live
- Signed overlay URLs should be effectively durable until regenerated by the streamer rather than treated as short-lived session URLs
- If a signed overlay URL becomes invalid, the overlay should show an error state rather than trying to silently re-sign itself

Future diagnostics and support:

- A later phase should add operator-facing diagnostics for remote iframe widgets so administrators and support users can distinguish app-origin failures from third-party widget failures without relying on raw browser console output alone
- Remote widget diagnostics should prefer structured health signals such as preflight results, recent load and timeout outcomes, origin and host metadata, and sanitized runtime failure summaries rather than persisting full third-party console logs by default
- Admin and support tooling may expose an explicit debug mode for deeper investigation, but the default product surface should avoid treating noisy third-party console output as the primary debugging interface

### 6.3 Twitch Bot Integration

The Twitch bot integration should be based on patterns proven by Super Sweet Bot and the open-source `chat-task-tic-overlay-infinity` project, while remaining a first-party Laravel implementation.

The early bot scope should remain task-list and Pomodoro focused. More general streamer and moderator automation inspired by tools such as FossaBot is intentionally deferred to a later phase.

The minimum backlog-oriented command set should cover:

- `!task`
- `!done`
- `!remove`
- `!edit`
- `!check`
- `!clear` for individual-user clearing and moderator usage
- `!cleardone` for individual-user clearing and moderator usage
- `!adel @user` for moderator usage

Aliases may take inspiration from `chat-task-tic-overlay-infinity`, but the first-party command design should remain intentional and documented.

Pomodoro command families should also cover:

- Start
- Pause
- Resume
- Break
- Reset

Implementation expectations:

- Twitch authentication
- Bot connection
- Queue-based event handling where useful
- Overlay state updates
- Viewer timer link generation when Pomodoro state changes

Bot transport and command behavior:

- The product should use a single application-level bot identity
- Inbound chat should use EventSub WebSocket delivery
- Outbound bot messages should use the Helix Send Chat Message API
- Bot commands should use a fixed `!` prefix with aliases and no per-streamer prefix customization
- `!task` without an argument should show help rather than infer behavior
- `!check` should check the user's last task
- `!clear` should clear the calling viewer's own tasks, while moderators can clear broader task scopes
- Bot feedback should be overlay-only rather than echoed back into chat by default
- Bot commands should normally operate only while the stream is live, with a streamer-only testing mode for offline configuration and testing
- Incoming chat events should be queued and processed in short batches, for example every 5 seconds
- Queue-based command handling should support configurable policy controls so streamers and application administrators can tune anti-spam behavior
- Tasks should start fresh on each stream rather than persist across streams
- Command design may directly copy equivalent behavior from Super Sweet Bot and Task Tic where that improves familiarity
- The Twitch runtime model should distinguish between a durable team-owned broadcaster/channel record and a per-broadcast `StreamSession` record so live command handling, task state, viewer timer state, and metering all attach to the same stream-session boundary
- Twitch broadcaster auth should be stored in a dedicated encrypted provider-auth model rather than directly on the `users` table, while application-level Twitch tokens should live in a separate token store
- EventSub subscription creation and refresh should be persisted and job-driven so stale or expired subscriptions can be detected and recreated

### 6.4 Viewer Timer Sync

The viewer timer sync feature allows viewers to synchronize their personal focus timer with the streamer's Pomodoro session so they can mute the stream, listen to their own music, and still stay aligned with the session.

Core behavior:

- When a Pomodoro focus session starts, the bot posts a message in Twitch chat
- The message contains a link to a viewer timer page hosted by the application
- Viewers can open the link to sync their timer with the stream session

Example chat message:

`Focus session started (50m)`

`Sync your timer here:`

`https://app.example.com/focus/{session_id}`

Viewer timer page should display:

- Remaining focus time
- Current session state such as focus or break
- Countdown timer
- Optional break notification
- Session progress indicator

Optional viewer features:

- Desktop notifications when session ends
- Minimal distraction UI or focus mode
- Suggestion to mute the stream if the viewer prefers their own music

Session sync behavior:

- The timer page must use the session start timestamp as the source of truth
- Remaining time should be calculated client-side
- The page should subscribe to session updates through Laravel Echo
- Client-side timer display should continue smoothly between server-originated realtime events

This feature should use the same Echo-based realtime model as widgets and the composer from the first implementation.

Trigger events for posting the viewer timer link:

- Pomodoro session starts
- Long break begins
- Session resets

Bot responsibilities for this feature:

- Update session state
- Push event into queue where appropriate
- Update overlay state
- Generate viewer timer link

Viewer timer URL format:

`/focus/{session_id}`

Viewer timer access modes:

- Public access
- Authenticated app access

Streamer control:

- Streamers should be able to configure whether viewer timer access is public or requires authenticated viewer access

Authenticated viewer access should plug into the application's general authentication system. When a user authenticates through the viewer path, the application should create a viewer-oriented team context for them if one does not already exist.

The session identifier must map to:

- Streamer
- Session start time
- Pomodoro duration
- Current state

Design goals for the viewer timer page:

- Lightweight
- Mobile-friendly
- Minimal distraction
- Fast-loading from a chat link

Viewer timer behavior:

- A JavaScript-dependent countdown is acceptable
- If no active session exists, the timer page should display an explicit no-active-session message
- Viewer timer sessions should become inaccessible immediately after the session ends
- Public timer access should rely on standard Laravel rate limiting
- When authenticated viewer access is enabled, unauthenticated users may still see a preview of timer state before they authenticate
- Authentication should unlock additional viewer interaction opportunities, such as seeing and interacting with their own task list alongside the timer

Product value:

- Viewer engagement during focus sessions
- Utility for viewers who prefer their own music
- An entry point for non-streamers to use the platform
- A potential growth channel for the product

### 6.5 Teams and Permissions

The conceptual source of truth should be the Team. Canvases, widgets, tasks, bot integrations, viewer sync surfaces, and later billing and theming concerns should all naturally attach to a team boundary.

Viewer and streamer participation should also fit the Team model. A user may interact with the product through a streamer-oriented team context, a viewer-oriented team context, or both, depending on how they authenticate and which surface they are using.

Team type should be enum-backed so viewer-oriented and streamer-oriented contexts remain explicit in the model.

The onboarding model should not require a viewer-oriented user to understand streamer products, and should not require a streamer-oriented user to understand viewer-specific surfaces. Team context should separate those experiences cleanly without forcing separate user identities.

User-facing terminology should use `viewer profile` and `streamer profile`, while `Team` remains the internal model primitive.

Streamer-oriented contexts may use more team-oriented language to imply future collaboration, but moderators and other collaborator roles are not expected in early phases.

Phase 1 team and role defaults:

- Teams should start as single-user teams
- The only additional non-owner role expected early is a moderator role
- Moderators may manage canvas layouts and related editor configuration
- Signed overlay URL generation and regeneration should remain streamer-owner responsibilities rather than moderator capabilities
- Viewer-oriented and streamer-oriented team contexts should be deletable independently
- User and team deletion should use soft deletes so ownership and membership associations remain intact for later auditability and potential restoration
- If a streamer's Twitch account is banned or suspended, the related team and overlay data should be suspended rather than removed

All entry paths should require acceptance of privacy policy and terms and conditions before the user completes onboarding into either viewer-oriented or streamer-oriented access.

Initial authentication assumptions:

- Laravel authentication via Jetstream and Fortify remains the baseline local email and password path
- Twitch and Discord should be available as optional registration and login providers through Laravel Socialite
- `User` must remain the only Laravel `Authenticatable` root and external providers must not replace the `users` table as the primary authentication model
- Previously unseen authenticated users should be routed into the relevant onboarding workflow rather than silently linked to an existing user by email
- Any enabled application login method may satisfy authenticated viewer access requirements
- YouTube should be described as a future provider and not part of the initial authentication implementation

Authentication and onboarding defaults:

- Social onboarding should capture privacy-policy and terms acceptance before redirecting to an external provider and should complete account creation only after a successful callback
- Twitch sign-in should start with the minimum identity and email scopes needed for authentication, then request broader broadcaster scopes only when streamer onboarding reaches bot and EventSub setup
- Discord authentication should request only the minimum identity and email scopes needed for Phase 1 account access
- Viewer authentication should request only the minimum scopes required for viewer functionality
- If a viewer later adds a streamer-oriented profile, they should re-authenticate to grant the broader streamer scope set
- The application should handle Twitch token refresh automatically
- If a user revokes Twitch access from Twitch settings, the application should detect that state and prompt re-authentication
- Provider-supplied email addresses must not automatically link to an existing local account
- Streamer onboarding should follow a step-based flow: legal acceptance, Twitch authentication, product selection, then feature-specific setup
- A later product-owned profile-management surface should allow an authenticated user to add the missing complementary viewer or streamer profile without exposing generic Jetstream team-management UI
- Product selection should at minimum distinguish between `Overlay Composer` only and task-widget features
- If task widgets are selected, onboarding should collect Pomodoro defaults before redirecting to the dashboard
- If only the composer is selected, onboarding may complete directly into the composer experience
- Streamer channel identity should be derived directly from the authenticated Twitch broadcaster identity rather than collected manually
- Each linked Twitch broadcaster account should map to one streamer-oriented team context
- One human user account may own multiple streamer-oriented team contexts through multiple linked Twitch broadcaster accounts

External identity and account-linking rules:

- Shared provider routes should use `/auth/{provider}/redirect` and `/auth/{provider}/callback`
- Authenticated account settings or security surfaces should own provider-link and provider-unlink flows
- `provider_auths` should be the user-linked boundary for external identity and provider-granted access
- `provider_auths` should store `user_id`, provider enum, `provider_user_id`, nullable provider-email snapshot, profile JSON, encrypted `access_token`, encrypted `refresh_token`, `token_expires_at`, granted `scopes`, `last_used_at`, standard soft-delete revocation state, and timestamps
- `provider_auths` should enforce global uniqueness on `(provider, provider_user_id)` while allowing multiple records for the same provider to belong to one user
- Third-party tokens must not be stored on `users`
- Tokens should be retained only when an ongoing provider capability requires them; login-only provider links may leave token fields null or clear them after use
- When a provider callback matches an existing `(provider, provider_user_id)` record, the application should log in the linked user and refresh the stored provider metadata and tokens as appropriate
- When a provider callback does not match an existing provider-auth record, registration should continue through the Fortify-backed user-creation path and attach the provider-auth record during onboarding completion
- If a provider does not supply an email address, the onboarding flow should collect a local email address and require application-managed email verification before the account is fully activated
- If a provider callback returns an email address that matches an existing local user and no provider-auth link exists, the application must not auto-link or log in that user and should instead route into a secure claim flow that requires authenticating the existing account first
- Linking an additional provider should be allowed only from an already authenticated account
- Linking should fail if the external provider identity is already attached to another user
- Sensitive provider link and unlink actions should require recent authentication or password confirmation
- A user must not be allowed to remove their last usable sign-in method
- Social-created accounts may add a local password later through account security settings and password setup should remain optional during social signup

Viewer-oriented onboarding expectations:

- A non-authenticated viewer arriving at a timer link should see a simple viewer registration screen before reaching the timer
- Viewer registration should use that timer-oriented entry path rather than the default dashboard `/register` route
- Completing the viewer path should create a viewer-oriented team context after privacy and terms acceptance
- If an authenticated user reaches the viewer registration path without a viewer-oriented team context, that path should create the missing viewer-oriented team rather than forcing a separate account
- Middleware should ensure authenticated viewer-path users have a viewer-oriented team context before continuing to timer surfaces
- Viewer onboarding, streamer onboarding, bot setup, and future signed-URL regeneration flows should use the shared persisted workflow system rather than ad hoc Livewire step counters

Minimum first-release viewer-oriented context should stay deliberately small:

- Team and user identity
- Team type
- Authentication provider identity
- Privacy policy acceptance timestamp
- Terms and conditions acceptance timestamp
- Basic created and last-seen timestamps

Viewer follow and timer-engagement signals should default to activity logging and audit trails in early phases rather than dedicated customer-facing data models, unless later product requirements explicitly promote those signals into standalone features.

Viewer-side history should remain activity-log based in early phases. That history may later become product-facing reference data, with the most likely early promotion path being a streamer-facing analytics view rather than a dedicated viewer product surface.

Viewer and streamer preferences should not be modeled through ad hoc per-surface storage. Preference data should move toward a first-class shared model such as `UserSetting` that can serve any user type.

`UserSetting` should be introduced in the first implementation phase for durable legal acceptance history and should remain the preferred shared model for later per-user structured state such as preferences.

The preferred `UserSetting` architecture should follow the pattern used in `~/Projects/hammock-app`: a user-owned settings store with enum-backed setting names, a JSON data payload for arbitrary structured values, and model-level helper methods for nested get and store operations.

Legal acceptance state should also be stored through `UserSetting`, with the data payload able to retain a list of acceptance timestamps and related policy-version metadata so future terms and privacy updates can be recorded without inventing a separate storage pattern.

Legal acceptance history is the first concrete Phase 1 requirement for `UserSetting`. Other user-preference uses may remain deferred until a delivery phase actually needs them.

The most obvious future candidates from the current PRD are:

- Viewer timer notification preferences
- Viewer timer focus-mode or distraction-reduction preferences
- General UI presentation preferences introduced by later product phases

Streamer-oriented onboarding expectations:

- Streamer onboarding should create a streamer-oriented team context after privacy and terms acceptance
- Discord-authenticated and locally authenticated users may create a general application account and reach the dashboard, but Twitch remains required before they can create or operate streamer-oriented broadcaster features
- An authenticated viewer entering the streamer registration flow should be told they already have a viewer profile and can continue creating a streamer profile
- An authenticated streamer visiting a viewer timer link should be told a viewer-oriented team context will be created if they do not already have one

Viewer-oriented navigation should remain minimal. The primary viewer surface is the viewer timer itself. If a user also has a streamer-oriented team context, the viewer surface may offer navigation into streamer functionality. If they do not, restrained upsell into streamer registration is a later-phase addition and should be scoped separately.

### 6.6 Theming

Early phases only need enough styling control to make the product usable and attractive.

Early theme controls may include:

- Accent color
- Typography selection
- Basic background treatment
- Animation intensity or motion preferences

Theme behavior defaults:

- Theme configuration should operate at the canvas level and apply consistently across the widgets rendered on that canvas
- Theme changes should apply in real time to the live overlay
- No explicit accessibility requirements are defined for early phases, though that may evolve later

Rich theme packs, premium aesthetics, and a deeper theming surface are intentionally deferred until after the composer product is in public beta.

## 7. Technical Architecture

### 7.1 Backend

Planned stack direction:

- Laravel
- MySQL
- Laravel Queues
- Laravel Livewire
- Laravel Echo
- Twitch authentication and bot integration
- Spatie activity logging

Primary backend responsibilities:

- User and team ownership
- Overlay state persistence
- Bot command ingestion and processing
- Canvas layout persistence
- Overlay authorization and signing
- Viewer timer session publishing
- Auditability for important domain events
- Billing and usage enforcement in later phases
- Persisted step-based product workflows

Workflow engine decision:

- The proprietary workflow system from `~/Projects/multistream-app` should be ported into this project wholesale and become the default state-management primitive for step-based Livewire flows
- The imported primitives should remain materially the same: `Workflow`, `BaseWorkflow`, `ArrayWorkflow`, `GuardResult`, `WorkflowStatus`, `WorkflowStore`, and the `LivewireWorkflow` concern
- The proprietary shared modal primitive from `~/Projects/multistream-app` should also be copied across wholesale, including `app/Livewire/Modal.php` and `resources/views/livewire/modal.blade.php`
- `workflow_stores` should persist `team_id`, `created_by_user_id`, `subject_type`, `subject_id`, `workflow_class`, `status`, and JSON `records`, with indexes supporting team, workflow-class, subject, and status lookups
- Livewire workflow components should declare `places()` and `transitions()`, mount through the shared workflow concern, and persist transitions through the shared store instead of duplicating step state in component properties
- Livewire modals should default to the shared modal component rather than each feature inventing its own browser event and Bootstrap wiring
- Guard failures should be recorded as part of workflow history with context so user-facing validation failures and operator debugging share the same transition record
- Initial first-party uses should include streamer onboarding, viewer onboarding, composer creation/editing wizards where applicable, bot setup, and signed overlay URL regeneration or rotation flows
- Initial first-party modal uses should include moderator actions, streamer confirmation flows, and other privileged UI interactions that need consistent Bootstrap and Livewire behavior
- The application should not introduce a second workflow or state-machine library for these server-driven flows unless the imported system proves insufficient in a later phase
- The imported workflow system should remain copied in this codebase long term; no package extraction work is currently required

Twitch runtime architecture decision:

- Architectural patterns from `~/Projects/cassly` should guide the Twitch integration where they fit this product, especially the separation of auth, channel identity, live session state, job boundaries, and long-running process management
- User-granted external provider access should live in a dedicated encrypted provider-auth model with provider enum, provider user ID, optional provider-email snapshot, profile metadata, token metadata, and explicit expiry and revocation handling
- The provider enum should conceptually cover `local`, `twitch`, `discord`, and future `youtube`, while only Twitch and Discord need external-provider implementation in the early phases
- Application-level provider tokens should live in a separate token model or service boundary and should not be conflated with user-granted provider-auth records
- Twitch-specific long-lived broadcaster tokens and app-level Twitch tokens should remain distinct from login-only provider links that do not require durable token retention
- A durable team-owned `Stream` record should represent the authenticated broadcaster/channel context for the product
- Each time the streamer goes live, the system should create a `StreamSession` record that owns the ephemeral runtime state for that broadcast, including task items, Pomodoro state, viewer timer state, inbound command history, heartbeats, and reconciliation metadata
- EventSub subscription creation should run through queue jobs and persist subscription state so stale subscriptions can be refreshed instead of recreated blindly
- A supervisor-managed manager command should maintain exactly one Twitch listener worker per durable broadcaster connection boundary, following the `cassly` pattern of a long-running manager that spawns dedicated workers
- Connection handling should follow a circuit-breaker style policy with failure counts, next-retry timestamps, explicit disabled/error states, and persisted connection logs for operator visibility

Recommendation on `Stream` model responsibility:

- Team remains the ownership, billing, authorization, and product-permissions boundary
- `Stream` should not become a second ownership primitive and should not duplicate Team responsibilities
- `Stream` exists to model durable Twitch broadcaster identity and channel-scoped operational data that does not naturally belong on Team itself, such as broadcaster IDs, display-name snapshots, authorizing provider-auth links, active subscription state, and connection/runtime metadata
- `StreamSession` then hangs off `Stream` to model one live or testable broadcast window with ephemeral task, Pomodoro, viewer timer, command, heartbeat, and reconciliation state
- This split is recommended because it keeps Team focused on application ownership while giving Twitch-specific runtime concerns a stable attachment point
- If the product permanently remains one broadcaster per streamer-oriented team and no channel-specific runtime or historical needs survive implementation, the model could later be folded inward, but the current recommendation is to keep `Stream`

Worker boundary recommendation:

- The recommended durable worker boundary is one listener worker per `Stream` or broadcaster auth connection
- This matches the EventSub and broadcaster-auth lifecycle better than a Team-scoped worker and avoids conflating ownership with transport concerns
- This boundary also works for offline testing and pre-live setup because the worker can exist before a specific `StreamSession` is active
- `StreamSession` should remain the runtime data boundary and may still have per-session queue jobs, but it should not be the primary long-lived listener process boundary
- A Team-scoped worker is acceptable only while the product is strictly one-stream-per-team, but it creates needless coupling
- A `StreamSession`-scoped worker is acceptable only for short-lived processors after events are persisted and should not be the main EventSub listener lifecycle

Infrastructure and deployment defaults:

- The target Phase 1 deployment shape may be a single-server environment
- Queue processing should default to the database queue driver with a supervisor-managed worker process model
- Queue separation should exist at least for `default`, `twitch`, `commands`, and `reconciliation` workloads so inbound Twitch event handling is not blocked by slower background work
- Initial concurrent overlay planning assumptions should be in the tens rather than hundreds or thousands
- Laravel Horizon should be used for queue monitoring
- Laravel Pulse and/or Telescope should be introduced for operational monitoring and debugging

For Phase 1 MVP, it is acceptable to implement parts of the conceptual model such as Teams and user authentication in a basic Laravel-first way if that accelerates delivery and preserves a clean upgrade path for later phases.

### 7.2 Frontend

Primary interface:

- Blade and Livewire

Frontend principles:

- Supplemental JavaScript should stay minimal
- New libraries should only be introduced when the requirement cannot be implemented cleanly with the Laravel, Livewire, Vite, and browser primitives already in use
- Specific library choices are deliberately left open for later planning

When productizing the application, a previously purchased Bootstrap 5 theme should be integrated during Phase 1 immediately after the initial Jetstream, activity logging, and parallel-testing foundation work so Tailwind-based authentication UI does not become expensive to unwind later. If that theme currently relies on Gulp, its asset pipeline should be migrated to Vite or another Laravel-native build approach, and Jetstream-added authentication and shared layout components should be refactored onto the Bootstrap theme so incoming UI is built on release-intent primitives from the start.

### 7.3 Communication Model

The product will use Laravel Echo together with Livewire's Echo support for primary realtime updates, including the viewer timer sync page.

Realtime implementation notes:

- Overlay and timer clients should automatically reconnect when the realtime connection drops using exponential backoff with jitter
- After reconnect, clients should perform a full state reload rather than attempt incremental catch-up in early phases
- A dedicated health check or status surface should exist for overlay delivery and bot connectivity monitoring
- Twitch listener workers should emit persisted connection and subscription logs so operator and debugging surfaces can diagnose broken EventSub delivery without reading raw process logs

### 7.4 Security and Audit

Security expectations for the overlay system:

- Laravel signed URLs for overlay delivery
- UUID-backed public identifiers
- Overlay routes should remain read-only and signed, while dashboard and mutating endpoints continue using standard CSRF protections
- No plaintext logging of secrets or tokens
- Team-scoped authorization boundaries
- Overlay content security policy should be strict, allow only required self, Twitch, and WebSocket origins, and treat usernames and task text as untrusted display data
- All bot command input and usernames should be sanitized before display on the overlay
- Authenticated streamers should be able to open and inspect their own overlays directly in a browser
- Visitors who reach overlay routes outside the expected authenticated or signed access paths should receive a simple static page with remediation steps rather than an opaque failure

Audit expectations:

- Important lifecycle changes should be recorded through Spatie activity logging
- Activity event names should be explicit and enum-backed
- Sensitive values must be excluded from logs
- All bot commands should be logged from Phase 1
- Activity monitoring should support a real-time dashboard view with adequate indexing
- Older activity logs should be archived after 3 months into a structured flat-file format that remains recoverable for later analysis

### 7.5 Metering and Stream Usage

The early paid and free-tier usage model should meter streamer usage based on Twitch-authenticated start and stop events.

The system should track enough event history to calculate billing-cycle streaming usage for the 40-hour free allowance.

Reliability safeguards should include Twitch API verification when events appear missed, delayed, duplicated, or otherwise inconsistent.

Widgets and composers should send keep-alive signals while actively in use so the backend can detect streams that may still be live even when a stop event has not yet been received.

The default keep-alive recommendation is a 30-second cadence with a 90-second timeout cutoff before Twitch verification is used to decide the next state.

If keep-alive signals stop after the timeout cutoff and no stop event has been received, the system should verify live state with Twitch before deciding how to close or continue the session.

The reconciliation model should use split authority:

- Overlay keep-alive is the source of truth for whether the overlay surface still appears active
- Twitch is the source of truth for whether the stream is actually live for metering and billing

If Twitch indicates the stream is offline but recent bot activity continues to arrive, the system should extend the active session window to the latest bot activity timestamp before finalizing closure.

The default bot-activity grace window should be 5 minutes from the latest qualifying bot activity timestamp, with a total extension cap of 15 minutes unless Twitch confirms live state again.

Both the default grace window and the total extension cap should be configurable through environment-level policy.

Qualifying bot activity should include meaningful user-triggered bot events associated with the current stream session. Background system events and unrelated automation noise should not extend grace.

The reconciliation process should use an explicit state-machine style approach with states such as `live`, `suspect`, `grace`, and `ended`.

Recommended reconciliation sequence:

- Enter `suspect` when Twitch reports offline but keep-alive or recent bot activity suggests the session may still be active
- Retry Twitch verification immediately, then on a fixed ladder at 30 seconds, 90 seconds, and 300 seconds
- Return to `live` only when Twitch confirms live state again
- Remain in `grace` while qualifying bot activity continues within the configured grace policy window
- Transition to `ended` when Twitch remains offline, keep-alive has stopped, and bot-activity grace has expired

Metering and free-tier defaults:

- The free allowance should be tracked per billing cycle rather than by calendar month or rolling window
- If a streamer exceeds the initial 40-hour allowance, they should receive an additional 40-hour grace period with upgrade prompts
- During the grace period, the product should restrict viewer-created personal tasks to 3 per session until upgrade or allowance reset
- Dashboard metering should show total hours this period, daily breakdowns, and per-session breakdowns
- If a stream spans a billing boundary, attribute the entire session to the billing period in which it started
- Metering should rely primarily on EventSub stream start and stop events, secondarily on overlay and widget activity, and tertiarily on bot activity

### 7.6 Development and Testing

Development testing defaults:

- Local development should include a developer testing panel that can mark a streamer channel or `StreamSession` as live for isolated product testing
- The developer panel should be able to inject synthetic chat messages through the same command-ingestion and queue path used by Twitch-originated messages, with the origin clearly tagged as test data
- The developer panel should support manual triggering of task and Pomodoro command flows so overlay behavior can be validated without relying on Twitch infrastructure
- IRC-oriented support should remain available in development tooling because it is useful for testing against live coworking streams and external bots such as Super Sweet Bot
- IRC-oriented development support should be explicitly non-production and should not replace EventSub as the production inbound chat model
- Stored fixtures and Twitch CLI should still be supported for deterministic local and CI-friendly testing, but they should sit alongside the developer panel rather than replace it
- Authentication and external-identity coverage should explicitly test local auth with no linked providers, first-time Twitch signup, first-time Discord signup, missing-provider-email flows, repeat login through an existing provider-auth, no-auto-link behavior for matching local email, authenticated provider linking, multiple Twitch identities on one user, duplicate-provider-link rejection, unlink protection for the last sign-in method, revoked-token re-authentication prompts, and viewer-timer access through any enabled login method

## 8. Conceptual Data Model

The application should treat Team as the root ownership boundary for the main product data.

Core entities:

- Users
- Teams
- Workflow stores
- Canvases
- Widget instances
- Streams
- Stream sessions
- Task items
- Pomodoro sessions
- Provider auth connections
- Provider app tokens
- Twitch subscriptions
- Bot command events
- Overlay heartbeats
- Connection logs
- Theme profiles
- Viewer timer sessions
- Activity log entries

Default modeling direction:

- Team-owned records should generally carry `team_id`
- User-authored records should generally carry `created_by_user_id`
- Canvas layout should be stored as team-owned configuration
- `WorkflowStore` should be the persisted state root for server-driven multi-step UI flows, optionally linked to a subject model and always scoped to the active team when team context exists
- `Stream` should represent the durable broadcaster/channel identity for a streamer-oriented team and should not replace Team as the ownership or authorization boundary
- `StreamSession` should represent one live broadcast window and own ephemeral overlay, task, command, viewer timer, and metering state for that broadcast
- Provider-auth records and app-token records should stay outside the `users` table and use encrypted token storage with explicit expiry handling
- Provider-auth records should support multiple linked identities of the same provider per user while preserving uniqueness of each external provider subject
- Provider-auth records should not use provider email alone as an account-matching key
- Event history should be auditable without relying only on operational logs
- Team context should support both streamer-oriented and viewer-oriented participation without requiring separate user identities
- Team type should be enum-backed

## 9. Development Phases

Task titles in this section are intended to be engineering-ready implementation slices. Each task should be specific enough for an agent to derive a concrete spec, affected files and systems, and validation approach without reinterpreting product intent.

### Phase 1: Foundation MVP

Goal:

Build the Laravel application foundation and a local working prototype.

Scope:

- Basic dashboard shell
- Bootstrap 5 theme migration onto Laravel Vite after the initial package foundation work
- Import of the shared workflow engine from `multistream-app`
- Import of the shared modal component from `multistream-app`
- Twitch auth, channel, and stream-session foundation shaped by the `cassly` architecture
- Task list widget
- Pomodoro widget
- Basic composer rendering foundation
- Initial authentication and team scaffolding where needed
- Initial activity logging foundation

Epics and Tasks:

#### `BP-EPIC-P1-01` Platform foundation and dashboard shell

Establish the Laravel product baseline and internal dashboard surfaces needed to support the first vertical slice.

- `BP-TASK-P1-01` DONE - Install Laravel Jetstream on the Livewire stack while keeping product-owned team models separate from Jetstream defaults.
- `BP-TASK-P1-02` DONE - Install and configure Spatie activity logging with published migrations and environment-safe defaults.
- `BP-TASK-P1-03` DONE - Add parallel test execution support to the local toolchain and project test commands.
- `BP-TASK-P1-04` DONE - Analyze the purchased Bootstrap 5 theme project, migrate its Gulp asset pipeline to Laravel Vite, and replace the existing Tailwind setup.
- `BP-TASK-P1-05` DONE - Refactor Jetstream-provided authentication screens and shared layout components onto the Bootstrap theme primitives.
- `BP-TASK-P1-06` Build the first authenticated dashboard layout, route group, and landing screens for setup, canvases, and streams.
- `BP-TASK-P1-07` Add empty, setup-required, and loading states for the initial dashboard surfaces.

#### `BP-EPIC-P1-02` Authentication, legal acceptance, and team/profile onboarding

Create the account, team-context, and onboarding foundation for streamer-oriented and viewer-oriented access.

- `BP-TASK-P1-08` DONE Implement `teams` and `team_user` persistence with enum-backed team types and membership roles.
- `BP-TASK-P1-09` DONE Implement `provider_auths` and `user_settings` persistence for external identity links and legal acceptance history.
- `BP-TASK-P1-10` DONE Add Twitch and Discord Socialite redirect and callback flows on `/auth/{provider}/redirect` and `/auth/{provider}/callback`.
- `BP-TASK-P1-11` DONE Build persisted onboarding workflows for local signup where a provider returns no email address; UI using the structure of the registration page should collect the email address from the user at this point. Will need branching logic if the email address already exists as a user, implemented as a Livewire component.
- `BP-TASK-P1-12` DONE Implement secure no-auto-link and account-claim behavior for provider callbacks that match existing local emails.
- `BP-TASK-P1-13` DONE Add feature tests for local auth, first-time social signup, and viewer versus streamer profile creation. Add Pest based browser tests for all aswell.
- `BP-TASK-P1-14` DONE Add Spatie activity logging to authentication surface implemented over the last few commits. No duplication of what is stored in workflows is required, however coherent logging is expected. Log event names should be enum backed. The local project `~/Projects/multistream-app` shows an adequate example of implementation, including a smart Model "tapping" architecture; however i'd go one better and look to introduce our own Activity model where any/all logging logic sits as the entrypoint rather than the spatie helper for easy extension later. This task should also encompass `BP-TASK-P1-17` and `BP-TASK-P1-18` - including tests mentioned in `BP-TASK-P1-19`

#### `BP-EPIC-P1-03` Shared workflow, modal, and activity primitives

Import the shared interaction primitives early so step-based flows and auditability become the default implementation path.

- `BP-TASK-P1-14` DONE Copy the shared workflow classes and Livewire integration from `~/Projects/multistream-app` into this codebase.
- `BP-TASK-P1-15` Copy the shared modal component from `~/Projects/multistream-app` and wire it into the app UI layer. Look at the commit it was introduced in to get a sense of what to bring over.
- `BP-TASK-P1-16` DONE Implement `workflow_stores` migration, model, and persistence wiring with team-aware ownership fields.
- `BP-TASK-P1-17` DONE Add enum-backed activity event definitions and attach logging to onboarding and core lifecycle actions.
- `BP-TASK-P1-18` DONE Exclude provider tokens and other sensitive values from activity logs and model change payloads.
- `BP-TASK-P1-19` DONE Add tests for workflow transition persistence, guard failure capture, and activity-log redaction.

#### `BP-EPIC-P1-04` Core overlay domain, local vertical slice, and developer testing tools

Deliver the first internal end-to-end overlay prototype without depending on live Twitch transport.

- `BP-TASK-P1-20` DONE Implement the Phase 1 schema and model skeletons for `streams`, `stream_sessions`, `canvases`, `widget_instances`, `task_items`, and `pomodoro_sessions`.
- `BP-TASK-P1-21` DONE Add factories and test helpers for teams, streams, canvases, tasks, and Pomodoro session setup.
- `BP-TASK-P1-22` DONE Build local-only testing widgets for a list of tasks and a pomodoro widget which can be used in future canvas testing.
- `BP-TASK-P1-23` DONE Build the canvas creation and general CRUD flow. 
- `BP-TASK-P1-23A` DONE Build the first composer workspace interactions for canvases, including widget add, move, resize, and visibility management on the editor surface.
- `BP-TASK-P1-24` Implement a single-canvas overlay render path driven by synthetic stream-session state.
- `BP-TASK-P1-25` Build a developer testing panel that can start or stop test sessions and inject synthetic chat commands.
- `BP-TASK-P1-26` Add focused feature tests for canvas rendering, widget updates, and developer-panel flows.

### Phase 2: Early Composer Release

Goal:

Release `Overlay Composer` as the first meaningful external product once the architecture and UI are stable enough.

Scope:

- Single browser-source overlay delivery
- Laravel signed overlay URLs with UUID canvas identifiers
- Basic composer editor controls
- Core Twitch bot command support
- Free offering up to 40 streaming hours per billing cycle
- Billing-cycle-based usage tracking and grace handling
- Initial Twitch event-based metering
- Recommended 30-second keep-alive cadence with 90-second timeout reconciliation

Epics and Tasks:

#### `BP-EPIC-P2-01` Composer editor and signed overlay delivery

Turn the internal canvas prototype into a secure single-browser-source product that streamers can configure and use in OBS.

- `BP-TASK-P2-01` DONE Implement drag-and-drop composer editing on canvas-dimension-driven artboards with persisted position, size, and z-index.
- `BP-TASK-P2-02` Add numeric form fallback controls for widget position, size, visibility, and stacking order.
- `BP-TASK-P2-03` Implement signed `/overlay/{canvas_uuid}` delivery routes with durable signatures and owner-driven regeneration.
- `BP-TASK-P2-04` Add preview mode and wireframe overlap warnings for intersecting widgets in the editor.
- `BP-TASK-P2-04A` Add remote-widget preview re-check controls so stored iframe preflight status can be refreshed after external embed headers or availability change.
- `BP-TASK-P2-05` Produce and execute an OBS browser-source QA matrix for signed delivery, invalid signatures, and non-1080p output sizing.

#### `BP-EPIC-P2-02` Twitch runtime, EventSub, and bot command handling

Connect the product to Twitch so live chat events drive task and Pomodoro state through the intended runtime model.

- `BP-TASK-P2-06` Expand streamer Twitch authorization scopes to cover broadcaster bot and EventSub runtime requirements.
- `BP-TASK-P2-07` Implement `twitch_subscriptions` persistence plus queued create, refresh, and recovery jobs.
- `BP-TASK-P2-08` Build EventSub WebSocket ingestion into `bot_command_events` with short-batch queued processing.
- `BP-TASK-P2-09` Implement the first command set for `!task`, `!done`, `!remove`, `!edit`, `!check`, `!clear`, `!cleardone`, `!adel`, and core Pomodoro commands.
- `BP-TASK-P2-10` Route command feedback to overlay state by default and gate offline runtime testing behind streamer-only test mode.

#### `BP-EPIC-P2-03` Metering, reconciliation, and free-tier enforcement

Track streaming usage accurately enough to support the free-tier offer and later billing decisions.

- `BP-TASK-P2-11` Persist Twitch-authenticated stream start and stop events against active `stream_sessions`.
- `BP-TASK-P2-12` Implement 30-second overlay heartbeat emission and 90-second timeout handling for active sessions.
- `BP-TASK-P2-13` Build reconciliation jobs for the `live`, `suspect`, `grace`, and `ended` session states with Twitch verification retries.
- `BP-TASK-P2-14` Persist billing-cycle usage summaries and per-session allocations using the session start billing period.
- `BP-TASK-P2-15` Enforce the 40-hour allowance, additional 40-hour grace period, and reduced personal-task limit during overage.

#### `BP-EPIC-P2-04` Operational monitoring, support surfaces, and release readiness

Add the visibility, admin tooling, and documentation needed to operate the early composer release safely.

- `BP-TASK-P2-16` Add dashboard status panels for overlay delivery, bot connectivity, EventSub subscription health, and reconciliation state.
- `BP-TASK-P2-17` Configure Horizon and Pulse and/or Telescope for queue monitoring and runtime diagnostics.
- `BP-TASK-P2-18` Build searchable support views for connection logs, command history, and activity timelines.
- `BP-TASK-P2-19` Write operator runbooks for Twitch auth recovery, signed URL regeneration, and reconciliation incident handling.
- `BP-TASK-P2-20` Add end-to-end smoke coverage for signed delivery, command ingestion, and metering behavior.

### Phase 3: Public Beta

Goal:

Move forward with the composer product in public beta before investing heavily in advanced theming.

Scope:

- Wider beta onboarding
- Team and moderator workflow refinement
- Composer editor improvements
- Viewer timer sync feature
- Full backlog-oriented task management flows
- Billing infrastructure prepared but not necessarily enabled
- Operational hardening for real stream usage

Epics and Tasks:

#### `BP-EPIC-P3-01` Viewer timer sync and access modes

Ship the viewer-facing timer experience so viewers can stay in sync with the streamer's Pomodoro session.

- `BP-TASK-P3-01` Implement the `/focus/{session_id}` viewer timer route and mobile-first timer page.
- `BP-TASK-P3-02` Publish and invalidate `viewer_timer_sessions` when focus starts, long breaks begin, and sessions reset or end.
- `BP-TASK-P3-03` Support public timer mode, authenticated timer mode, and preview-before-login gating from the same session source.
- `BP-TASK-P3-04` Subscribe timer clients to Echo session updates while maintaining a smooth client-side countdown between events.
- `BP-TASK-P3-05` Add tests for no-active-session messaging, ended-session invalidation, authenticated gating, and public rate limiting.

#### `BP-EPIC-P3-02` Moderator and team workflow refinement with permission polish

Refine collaboration boundaries so streamer teams can safely operate the product in beta.

- `BP-TASK-P3-06` Add policy-backed `moderator` permissions for canvas editing and approved stream-operation actions.
- `BP-TASK-P3-07` Enforce owner-only access for signed URL rotation, team suspension handling, and other privileged streamer settings.
- `BP-TASK-P3-08` Build profile and team switching flows for accounts that hold both viewer and streamer contexts.
- `BP-TASK-P3-09` Log moderator and owner actions through enum-backed activity events.
- `BP-TASK-P3-10` Add policy and feature tests for moderator access, owner-only flows, and suspended-team lockouts.

#### `BP-EPIC-P3-03` Backlog task-management expansion and composer polish

Expand the product from the initial task widget into fuller backlog-oriented stream workflows while improving editor usability.

- `BP-TASK-P3-11` Implement session-scoped backlog services and command handlers for add, edit, remove, check, clear, done, and moderator delete flows.
- `BP-TASK-P3-12` Persist archived viewer task history with status grouping, insertion-order preservation, and overflow scrolling behavior.
- `BP-TASK-P3-13` Add per-viewer active-task caps and related anti-noise policy controls to task presentation logic.
- `BP-TASK-P3-14` Implement the beta-feedback composer fixes for preview toggles, overlap feedback, and layout adjustment workflows.
- `BP-TASK-P3-15` Add regression coverage for moderator task controls and task reset at new stream-session start.

#### `BP-EPIC-P3-04` Beta onboarding, billing readiness, and support operations

Prepare the product for broader beta usage without prematurely turning on full production billing.

- `BP-TASK-P3-16` Implement beta onboarding states for invite acceptance, setup completion, and support handoff across streamer and viewer entry paths.
- `BP-TASK-P3-17` Add billing data models and service boundaries behind a disabled feature flag for later charge collection.
- `BP-TASK-P3-18` Add billing-readiness dashboard surfaces and feature-flagged account messaging without enabling production charging.
- `BP-TASK-P3-19` Add operational alerts and recovery playbooks for degraded Twitch connectivity, EventSub drift, and overlay delivery failures.
- `BP-TASK-P3-20` Publish beta setup guides, support runbooks, and known-limitations documentation for pilot users.

### Phase 3.5: Theming Expansion

Goal:

Introduce the richer theming layer after the composer beta has proven the core product direction.

Scope:

- Theme packs
- Reusable visual systems
- Premium presentation polish

Epics and Tasks:

#### `BP-EPIC-P35-01` Expanded canvas theme controls

Add richer but still systematized theme controls that improve the canvas editing and live overlay experience.

- `BP-TASK-P35-01` Implement `theme_profiles` settings for accent color, typography, background treatment, and motion intensity.
- `BP-TASK-P35-02` Apply canvas theme changes consistently to widgets, preview surfaces, and live overlay rendering in real time.
- `BP-TASK-P35-03` Build theme-editor defaults, reset actions, and invalid-configuration fallbacks.
- `BP-TASK-P35-04` Add regression coverage for theme persistence, live updates, and canvas-level theme application rules.

#### `BP-EPIC-P35-02` Reusable theme packs and presentation polish

Package the theming system into reusable visual sets that lift the product beyond the early beta look.

- `BP-TASK-P35-05` Implement the theme-pack schema, asset structure, and registration path for curated overlay presentations.
- `BP-TASK-P35-06` Build the first shipped theme packs for overlay widgets, viewer timer, and composer surfaces.
- `BP-TASK-P35-07` Document how theme packs are added, versioned, and supported by the product team.
- `BP-TASK-P35-08` Add visual-regression and manual QA coverage for theme packs across common canvas layouts and OBS/browser-source previews.

### Phase 4: Production Launch

Scope:

- Billing enablement
- Expanded widget catalog
- More mature usage enforcement
- Performance and infrastructure hardening

Epics and Tasks:

#### `BP-EPIC-P4-01` Billing enablement and entitlement enforcement

Turn the prepared billing foundation into a production-ready entitlement model tied to real usage.

- `BP-TASK-P4-01` Implement billing-provider integration and subscription lifecycle handling for eligible streamer teams.
- `BP-TASK-P4-02` Enforce paid and free-tier entitlements against metered usage, viewer-task limits, and gated product capabilities.
- `BP-TASK-P4-03` Build upgrade, downgrade, cancel, and overage messaging flows around entitlement state.
- `BP-TASK-P4-04` Add finance and support reconciliation views for billed usage, session adjustments, and dispute handling.

#### `BP-EPIC-P4-02` Performance, security, and infrastructure hardening

Prepare the platform to operate reliably under production load and security expectations.

- `BP-TASK-P4-05` Load-test overlay delivery, Echo reconnects, and queued Twitch processing at projected production concurrency.
- `BP-TASK-P4-06` Enforce production security controls for signed routes, CSP, token redaction, and activity-log archival.
- `BP-TASK-P4-07` Move deployment and worker topology beyond the initial single-server assumption with separated runtime concerns.
- `BP-TASK-P4-08` Run production-readiness exercises for Twitch outages, queue backlog, websocket loss, and high-usage recovery.

#### `BP-EPIC-P4-03` Launch readiness, support operations, and post-launch widget expansion

Complete the launch package with operational readiness and the first broader expansion of the widget surface.

- `BP-TASK-P4-09` Implement the widget-catalog extension mechanism and ship the first post-launch additions through the existing composer contracts.
- `BP-TASK-P4-10` Finalize production support workflows, incident escalation paths, and customer-facing status communications.
- `BP-TASK-P4-11` Publish launch documentation, onboarding updates, and release-positioning material for production rollout.
- `BP-TASK-P4-12` Review launch telemetry and convert stabilization findings into the first post-launch backlog.

### Future Phase: Community Automation

Goal:

Introduce an advanced Twitch bot automation layer for streamer and moderator workflows after the overlay product, viewer sync surfaces, and production runtime are stable.

Scope:

- Custom commands with aliases, role-based access, cooldowns, and optional expiry
- Keyword-triggered responses and actions, including grouped phrase matching where useful
- Counters and template-variable driven bot responses
- Timed announcements and recurring bot timers
- Moderator and operator controls with strong auditability
- Team-scoped collaboration and permissions for automation management

Architecture defaults:

- Automation configuration remains team-owned and permissioned through the existing team model
- Live automation execution remains attached to the existing `Stream` and `StreamSession` runtime boundaries
- Automation events should flow through the existing queued bot command and event-processing pipeline
- Automation actions should remain auditable through activity logging and command-history style records

Non-goals for the first automation phase:

- No promise of FossaBot command syntax parity
- No import or migration tooling for existing FossaBot configurations
- No media-request system
- No commitment to full `nuke` or mass-moderation parity
- No broad channel-management surface such as title or game management unless a later PRD revision explicitly promotes it

Planning defaults:

- This phase is inspired by FossaBot-style automation patterns, not interoperability or migration goals
- This work should remain strictly sequential after the earlier overlay, viewer-sync, beta-hardening, and production-launch phases
- This phase should extend the existing application-level bot identity and queue/runtime architecture rather than introducing a separate bot product boundary

Detailed `BP` epic and task breakdown is intentionally deferred until Phases 1 through 4 are complete.

### Future Phase: Twitch Extension

Purpose:

Provide timer synchronization without requiring viewers to leave Twitch.

Extension capabilities:

- Current Pomodoro state
- Remaining focus time
- Break countdown
- Optional viewer notifications

Potential extension placements:

- Panel extension below stream
- Overlay extension
- Component extension

Initial recommendation:

Panel extension, because it is simpler to implement and less intrusive.

Extension data source:

`/api/session/{channel_id}`

Returned data should include:

- `session_state`
- `remaining_time`
- `session_duration`
- `session_type`

The extension may update via Echo where practical, with polling as an acceptable fallback if Twitch extension constraints make Echo unsuitable.

Extension planning defaults:

- Twitch Extension work should remain strictly sequential after earlier phases rather than being developed concurrently with Phase 3
- The extension should share the same session API shape as the viewer timer page

Detailed `BP` epic and task breakdown is intentionally deferred until Phases 1 through 4 are complete.

## 10. Success Metrics

- Active streamer teams
- Billing-cycle overlay streaming hours
- Overlay load and reconnect performance
- Bot command success rate
- Viewer timer link engagement
- Retention from initial composer usage to repeat usage

## 11. Open Questions

No current open questions are recorded in the PRD.

## 12. Recommended Next Steps

The immediate implementation priority is to complete the Phase 1 foundation in a sequence that preserves the intended domain boundaries instead of jumping straight to widgets, bot commands, or billing behavior.

Recommended execution order:

1. Finalize the concrete Phase 1 schema and model boundaries for teams, workflows, provider auth, streams, stream sessions, canvases, widgets, and auditability.
2. Install and configure the baseline product stack assumed by this PRD, especially Livewire, activity logging, and the realtime foundation needed for overlays and later viewer timer sync.
3. Replace stock Laravel authentication assumptions with Jetstream and Fortify local auth plus Twitch and Discord onboarding and team-context creation for streamer and viewer profiles.
4. Import the shared workflow engine and shared modal primitive before building onboarding, setup, or rotation flows so persisted workflow state becomes the default path from the start.
5. Implement the durable Twitch domain layer first: provider auth storage, app token storage, `Stream`, `StreamSession`, subscription persistence, and queue/job boundaries.
6. Deliver an internal vertical slice with a basic dashboard shell, one canvas, signed overlay delivery, task widget, Pomodoro widget, and a developer testing panel using synthetic session state.
7. Add live Twitch EventSub ingestion, queued command handling, and session reconciliation only after the local vertical slice is stable.

Documentation for this sequencing should stay split by concern:

- This PRD remains the source of truth for product requirements and phased scope.
- `docs/RUNBOOK.md` should track the implementation state of the repository at `HEAD`.
- Detailed execution documents should live under `docs/backlog/`, starting with [docs/backlog/001-concrete-schema.md](./backlog/001-concrete-schema.md).

### Developer Guide For Agent-Led Execution

When guiding agents through this PRD, the developer should use the documents in a fixed order and keep each task scoped to one durable outcome.

Recommended operating flow:

1. Start with this PRD to identify the current phase, the relevant product area, and any explicit non-goals.
2. Read `docs/RUNBOOK.md` next to understand what already exists at `HEAD`, what the current priorities are, and whether a backlog document already covers the work.
3. Use the numbered file in `docs/backlog/` only when the task needs deeper implementation guidance than the PRD and runbook provide.
4. Give the agent one concrete slice at a time, such as schema foundation, auth foundation, workflow import, or one vertical slice of overlay behavior.
5. Ask the agent to update the durable documentation as part of the task whenever implementation state changes, and to remove or consolidate temporary planning notes before the task is complete.

Task framing guidance for developers:

- Frame work in terms of phase and boundary, for example: "Phase 1 schema foundation for teams and streams" rather than "build Twitch integration".
- Prefer end-to-end slices that leave the repository in a coherent state with code, tests, and docs aligned.
- Keep agents from starting Phase 2 or Phase 3 concerns early unless the Phase 1 prerequisite named in this section is already in place.
- Require agents to reference the PRD and runbook instead of copying requirement text into new files.
- Treat backlog documents as implementation aids, not parallel product specifications.

Review guidance for developers:

- Check that the task stayed inside the current phase and did not silently pull later-phase requirements forward.
- Check that team ownership, stream-session boundaries, signed overlay security, and auditability still match the architectural decisions earlier in this PRD.
- Check that `docs/RUNBOOK.md` reflects the repository's new state at `HEAD`.
- Check that any backlog document added or edited remains current, narrowly scoped, and non-duplicative.
