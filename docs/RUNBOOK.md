# Project Runbook

This runbook tracks the implementation state of the repository at `HEAD`.

## Current State

- The repository is a Laravel 12 application with product-owned authentication, team semantics, workflow primitives, and early Phoenix-backed UI foundations in place.
- `docs/PRD.md` defines the product direction, architecture, and phased scope.
- `docs/agents/*.md` now hold agent-facing operating guidance. This file owns the current implementation narrative and active priorities.
- Initial implementation should still follow the Phase 1 Foundation MVP before public Twitch bot or billing work expands the surface area.
- The core Phase 1 overlay domain foundation now includes schema, model, enum, and factory coverage for `streams`, `stream_sessions`, `canvases`, durable proprietary `widgets`, `canvas_widgets`, `follower_goal_states`, `task_items`, and `pomodoro_sessions`.
- Canvas CRUD is now implemented under `/canvases` with team-scoped list and edit pages, owner-only metadata mutation, soft-delete archive and restore behavior, and a first-pass composer workspace on the edit screen.
- The composer workspace now uses each canvas record’s configured dimensions as the single geometry basis for editing and runtime rendering, with a bounded viewport that auto-fits the stage to the available workspace area, a Livewire-backed layer rail, an Alpine-owned lifecycle, Blade-owned stage templates and copy, a JS-managed `wire:ignore` stage surface, a Moveable interaction module for single-widget drag and resize behavior, and overlay-origin iframe previews managed through a dedicated preview-session module. PHP remains the canonical widget-geometry authority while client-side JS only maintains transient draft geometry during pointer interaction. Widget geometry now separates visible frame size from authored content size with persisted crop offsets and per-widget editor defaults, so normal resize preserves the visible-source ratio, `Alt`/`Option` drag crops, `Shift` drag stretches, `Ctrl`/`Cmd` drag edits source bounds, reset controls sit above the canvas, canvas metadata changes uniformly rescale widget layouts from the top-left origin, and the published overlay uses the same scale-and-crop wrapper model as the editor. The editor also now includes a session-local undo/redo stack for non-destructive layout changes such as geometry, reset, visibility, and z-order edits, plus a confirmed widget-delete flow that permanently removes placements outside the undo stack. Proprietary widget placements can now either quick-create a new shared widget or attach an existing shared widget, while remote embeds remain canvas-scoped advanced placements on the same `canvas_widgets` table. Explicit zoom, pan, and related viewport controls remain deferred for a later iteration.
- Overlay delivery now has split-origin plumbing through `APP_URL` and `APP_OVERLAY_URL`, signed relative routes for overlay pages and preview shells, route-family frame-ancestor headers, and first-pass remote widget hardening that rejects app-origin, overlay-origin, loopback, and private-network targets before preflight inspection.
- A proprietary widget library now exists under `/widgets`, with proprietary-only creation, filtering, canonical `widgets/{widget}` management pages, standalone published URLs, archive and restore behavior, and owner-only management for provider-gated widget types.
- Provider-backed widget flows now run through the product integration model rather than the removed local Spotify seam. `/integrations` exposes owner-only Twitch and Spotify connection health, usage counts, reconnect and disconnect actions, and drill-in access to dependent widgets. Provider credentials remain user-owned and are resolved team-side through the current team owner.
- The first generalized proprietary widget slice is implemented end to end for Task List, Pomodoro Timer, Follower Goal, and Spotify Now Playing. Follower Goal state is stored separately from durable widget config, Spotify uses the same widget-definition registry and lifecycle model as the other proprietary widgets, and lifecycle health is tracked separately from standalone publication state.
- Local-only widget preview tooling now exists only at `/local/widgets`, `/local/widgets/task-list`, and `/local/widgets/pomodoro` in the `local` and `testing` environments. The temporary `/local/widgets/spotify` seam has been removed.

## Workflow-Backed Auth And Onboarding At `HEAD`

- Workflow primitives from `~/Projects/multistream-app` are part of this repository: `BaseWorkflow`, `ArrayWorkflow`, the `LivewireWorkflow` concern, `WorkflowStore`, and the `workflow_stores` table with guest-safe nullable `team_id` and `created_by_user_id`.
- Guest workflows are supported through session-backed persistence with `saveStore(useSession: true)`. The `ArrayWorkflow` Livewire payload preserves the backing store identity across hydration so guest onboarding updates a single workflow store across requests.
- Team persistence uses product-owned semantics on top of Jetstream-compatible internals: `teams.user_id` remains the owner foreign key, `teams.type` and `team_user.role` are string columns with application-layer enum casts, `users` and `teams` use soft deletes, standard `/register` creates a streamer-oriented team by default, and the generic Jetstream team-management surface is disabled.
- External-auth persistence includes `provider_auths` for encrypted provider identity links with soft-delete revocation and `user_settings` for one `legal_acceptance_history` payload per user with `current` timestamps plus append-only `history` entries.
- Provider auth records also persist a normalized HTTPS `avatar_url` when a provider returns one, while retaining the raw provider profile payload separately.
- Twitch and Discord OAuth redirect and callback flows are implemented for both login and registration. Guest round-trip state persists through `SocialAuthHandshakeWorkflow` rather than a custom session payload.
- Existing linked provider auths complete inside the handshake workflow before local login and redirect, and callback metadata refreshes on subsequent logins before the handshake workflow closes.
- First-time social registrations with a provider email complete inside the handshake workflow, provision the default streamer team, and then redirect into the standard email verification flow.
- Missing-provider-email social registration hands off from `SocialAuthHandshakeWorkflow` into `SocialRegistrationWorkflow` and routes the user to `/register/social-email`, where the guest Livewire flow collects a local email address, creates an unverified user, provisions the default streamer team, attaches the provider link, persists legal acceptance history, and redirects to the verification notice.
- Existing local email matches without a linked provider auth do not auto-link in Phase 1. They hand off into the registration workflow and stop in a blocked state.
- Auth and onboarding now write activity through a product-owned wrapper on top of Spatie with enum-backed event names, a custom `Activity` model, nullable `team_id` support on `activity_log`, original-page request-path capture for Livewire plus triggering component and method metadata, and whitelist-only payloads that exclude provider tokens and raw provider profiles.
- Local-only social debug overrides are supported on the redirect route with `debug=no_email` or `debug=<valid-email>`. The override is applied app-side after callback resolution for both Twitch and Discord and is ignored outside the local environment.
- Auth and onboarding now also have Pest browser coverage under `tests/Browser/` using a testing-only localhost OAuth harness from `routes/testing.php` plus a testing-only Socialite binding. The harness seeds deterministic Twitch and Discord callback scenarios without external HTTP traffic.
- Fortify email verification is enabled globally. `User` implements `MustVerifyEmail`, local registration produces unverified users, and first-time social registration also lands in the standard verification flow.
- Transactional email now uses a product-owned Laravel Markdown mail theme under `resources/views/vendor/mail`, with branded verification, password reset, and team invitation content while preserving framework-native delivery flows.

## Phoenix Adoption At `HEAD`

- The shared UI shell has moved away from the stock Tailwind-driven Jetstream presentation to a product-owned Bootstrap 5 and Phoenix runtime layer compiled through Vite.
- Runtime CSS imports the upstream Phoenix SCSS surface through local wrappers under `resources/css/phoenix`, while JavaScript adoption remains selective and product-owned under `resources/js/phoenix`.
- The committed upstream Phoenix snapshot lives at `resources/third-party/themes/phoenix-v1.24.0` and is treated as read-only source material.
- The authenticated application shell supports Phoenix-inspired `vertical`, `horizontal`, `combo`, `dual-nav`, and `topnav-slim` layout variants through one `x-app-layout` API, with `vertical` as the default for current application pages.
- The authenticated shell now exposes an opt-in `contentTop` region inside `.content` for lightweight top-of-content navigation. Canvas pages use it for a Phoenix-inspired sticky breadcrumb band, while page titles, summaries, and actions remain in the normal header below.
- Authenticated navigation is product-owned and component-driven through shared Blade shell components plus centralized PHP menu definitions under `app/Support/Shell`.
- The content-top band uses product-owned styling plus a local `SimpleBar` integration for horizontal overflow. Active scrollspy behavior is still deferred; future work should use the Phoenix widget references at `resources/third-party/themes/phoenix-v1.24.0/public/widgets.html`, `resources/third-party/themes/phoenix-v1.24.0/src/pug/mixins/widgets/WidgetNavbar.pug`, and `resources/third-party/themes/phoenix-v1.24.0/src/scss/theme/_mixed.scss`.
- Shared modal UI now routes through one product-owned `x-modal` shell plus a shared `App\Livewire\Modal` host for modal-mounted Livewire children or Blade partials. The shell now delegates show/hide, backdrop timing, scroll locking, and focus trapping to the Bootstrap modal plugin while preserving product-owned Blade composition and Livewire payload wiring; the older `x-dialog-modal` and `x-confirmation-modal` wrappers have been removed.
- Shared offcanvas UI now routes through one product-owned `x-offcanvas` shell plus a shared `App\Livewire\Offcanvas` host that mirrors the modal-host contract for metadata forms and other side panels, with Bootstrap offcanvas runtime handling the slide-in/backdrop lifecycle.
- Overlay triggers now route through generic `overlay-*` browser events, the `App\Livewire\Concerns\InteractsWithOverlays` trait for Livewire callers, and an `x-overlay-trigger` Blade component for markup-driven opens so host ids and payload shapes stay centralized.
- The shared shell utility avatar now falls back from app-managed profile photo to the most recently used linked provider avatar, then initials, with client-side recovery if the remote image fails to load.
- Guest auth pages remain on separate guest layouts. The guest auth document shell supports explicit `simple` and `card` variants, with the login screen rebuilt from the Phoenix simple sign-in reference and a reusable card shell prepared for later registration work.
- Font Awesome is part of the local Vite asset pipeline and is the primary icon library for product-owned UI work.

## Current Priorities

1. Continue building Phase 1 team-scoped product features on top of the implemented auth and workflow foundation.
2. Keep workflow-backed onboarding and verification behavior aligned with the feature test suite as later claim and link flows are added.
3. Deliver the first end-to-end team workspace slice for canvases, widgets, tasks, and Pomodoro behavior before integrating live Twitch transport.

## Active Backlog Documents

- [001-concrete-schema](./backlog/001-concrete-schema.md): proposed Phase 1 schema and model boundaries derived from the PRD.
- [002-overlay-origin-and-widget-preview-architecture](./backlog/002-overlay-origin-and-widget-preview-architecture.md): proposed overlay-origin, preview, and remote-widget trust-boundary plan.

## Maintenance Rules

- Update this file when the implementation state or immediate execution priorities materially change.
- Keep requirement detail in `docs/PRD.md`.
- Keep agent-facing conventions in `docs/agents/*.md`.
- Keep deep-dive planning in numbered `docs/backlog/` files and remove or consolidate stale backlog documents as decisions become part of the implemented system.
