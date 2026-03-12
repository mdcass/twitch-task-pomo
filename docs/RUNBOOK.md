# Project Runbook

This runbook tracks the implementation state of the repository at `HEAD`.

## Current State

- The repository is still a stock Laravel 12 application with the default authentication model and no product-specific domain implementation.
- `docs/PRD.md` defines the product direction, architecture, and phased scope.
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
