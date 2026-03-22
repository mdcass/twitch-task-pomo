# Project Runbook

This runbook tracks the implementation state of the repository at `HEAD`.

## Current State

- The repository is still a stock Laravel 12 application with the default authentication model and no product-specific domain implementation.
- `docs/PRD.md` defines the product direction, architecture, and phased scope.
- The shared UI shell has been migrated from the stock Tailwind-driven Jetstream presentation to a product-owned Bootstrap 5 and Phoenix runtime layer compiled through Vite.
- Runtime CSS now imports the full upstream Phoenix SCSS surface through a local wrapper under `resources/css/phoenix`, while JavaScript adoption remains selective and product-owned under `resources/js/phoenix`.
- The committed upstream Phoenix reference snapshot lives at `resources/third-party/themes/phoenix-v1.24.0` and is treated as read-only source material for future UI work.
- Durable Phoenix adoption notes and the current upstream-to-local mapping live in `docs/agents/theme.md`.
- The authenticated application shell supports Phoenix-inspired `vertical`, `horizontal`, `combo`, `dual-nav`, and `topnav-slim` layout variants through one `x-app-layout` API, with `vertical` as the default for current application pages.
- Authenticated navigation is product-owned and component-driven through a shared Blade component shell plus centralized PHP menu definitions, while guest auth pages remain on their separate guest layout variants.
- The guest auth document shell now supports explicit `simple` and `card` variants, with the login screen rebuilt from the Phoenix simple sign-in reference and a reusable card shell prepared for the later registration redesign.
- Font Awesome is now part of the local Vite asset pipeline and is the primary icon library for product-owned UI work.
- Team persistence now uses product-owned semantics on top of Jetstream-compatible internals: `teams.user_id` remains the owner foreign key, `teams.type` and `team_user.role` are string columns with application-layer enum casts, `users` and `teams` use soft deletes, standard `/register` creates a streamer-oriented team by default, and the generic Jetstream team-management surface is disabled.
- Initial implementation should follow Phase 1 Foundation MVP before attempting public-facing Twitch bot or billing work.

## Current Priorities

1. Establish the Phase 1 foundation models, workflows, and package baseline.
2. Replace default authentication assumptions with Jetstream and Fortify local auth plus Twitch and Discord identity-provider onboarding and team contexts.
3. Deliver a local end-to-end vertical slice for one canvas with task and Pomodoro widgets before integrating live Twitch transport.

## Active Backlog Documents

- [001-concrete-schema](./backlog/001-concrete-schema.md): proposed Phase 1 schema and model boundaries derived from the PRD.

## Maintenance Rules

- Update this file when the implementation state or immediate execution priorities materially change.
- Keep requirement detail in `docs/PRD.md`.
- Keep deep-dive planning in numbered `docs/backlog/` files and remove or consolidate stale backlog documents as decisions become part of the implemented system.
