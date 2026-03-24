# Project Runbook

This runbook tracks the implementation state of the repository at `HEAD`.

## Current State

- The repository is a Laravel 12 application with product-owned authentication, team semantics, workflow primitives, and early Phoenix-backed UI foundations in place.
- `docs/PRD.md` defines the product direction, architecture, and phased scope.
- `docs/agents/*.md` now hold agent-facing operating guidance. This file owns the current implementation narrative and active priorities.
- Initial implementation should still follow the Phase 1 Foundation MVP before public Twitch bot or billing work expands the surface area.
- The core Phase 1 overlay domain foundation now includes schema, model, enum, and factory coverage for `streams`, `stream_sessions`, `canvases`, `widget_instances`, `task_items`, and `pomodoro_sessions`.
- Canvas CRUD is now implemented under `/canvases` with team-scoped list and edit pages, owner-only metadata mutation, soft-delete archive and restore behavior, and a workspace placeholder that defers real composer interactions to a follow-on task.
- Local-only widget preview tooling now exists at `/local/widgets`, `/local/widgets/task-list`, `/local/widgets/pomodoro`, and `/local/widgets/spotify` in the `local` and `testing` environments. The launcher at `/local/widgets` now requires app authentication so a developer can connect or disconnect Spotify for testing, while the task, pomodoro, and Spotify widget routes stay outside the authenticated shell for OBS-style preview loading.
- The Spotify local widget uses a separate local/testing-only Socialite connect flow, stores Spotify credentials in `provider_auths`, and renders through a Livewire polling widget at `/local/widgets/spotify`. The Spotify preview intentionally resolves the most recently linked Spotify auth record as a local/testing seam and is not part of the guest sign-in flow or the team-scoped composer model yet.

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

## Maintenance Rules

- Update this file when the implementation state or immediate execution priorities materially change.
- Keep requirement detail in `docs/PRD.md`.
- Keep agent-facing conventions in `docs/agents/*.md`.
- Keep deep-dive planning in numbered `docs/backlog/` files and remove or consolidate stale backlog documents as decisions become part of the implemented system.
