This repository is a Laravel-first streaming overlay application for Twitch productivity and co-working creators.

# Project Rules

- Primary code lives in `app/`, `database/`, `resources/`, `routes/`, `docs/`, and `tests/`.
- Do not modify files in `vendor/`.
- Keep changes task-scoped and minimal.
- Do not change coding style or formatting outside edited code unless the task requires it.
- Do not modify config or build files unless the task requires it.
- Do not commit unless explicitly asked.
- Ignore unrelated local changes and do not clean up work you did not author.
- Re-check logic and tests after editing when appropriate.

# UI Controller Strategy

- Authenticated app-shell UI controllers should stay thin and resource-shaped.
- For app-shell UI, controllers should authorize, resolve route models, and return the Blade shell only; page state, queries, filters, and mutations should live in embedded Livewire components.
- Do not introduce full-page Livewire routes for authenticated product UI; mount Livewire inside the app shell instead.
- Workflow or OAuth handshakes, public overlay delivery, and local or testing-only tooling may use separate thin controllers outside the app-shell UI rule.

# Documentation Hygiene

- `docs/PRD.md` is the product source of truth for requirements and phase scope.
- `docs/RUNBOOK.md` is the source of truth for the repository's current implementation state at `HEAD`.
- `docs/agents/*.md` are agent-facing operating guides. Keep them focused on stable conventions, entrypoints, and boundaries rather than current-state narrative.
- Additional planning or execution documents must live in `docs/backlog/` as numbered files such as `001-concrete-schema.md`.
- Backlog documents should extend the PRD and runbook, not restate them.
- Fold durable outcomes from temporary planning notes back into the runbook or PRD, or remove the temporary note before the task is complete.
- When a backlog document becomes stale because its decisions are now implemented, update or delete it in the same task.

# Read Order

1. `AGENTS.md`
2. `docs/agents/overview.md`
3. Relevant topical docs in `docs/agents/`
4. `docs/RUNBOOK.md`
5. `docs/PRD.md`

# Consult Map

- Read `docs/agents/overview.md` first for stack, structure, and documentation boundaries.
- Read `docs/agents/architecture.md` before changing domain model boundaries, tenancy, authorization, logging, or signed/realtime behavior.
- Read `docs/agents/frontend.md` before changing frontend ownership boundaries, Phoenix-backed UI, or shared shell behavior.
- Read `docs/agents/blade.md` before changing Blade layouts, shared Blade components, or page-composition views.
- Read `docs/agents/livewire.md` before changing `app/Livewire/`, `resources/views/livewire/`, or shared Livewire primitives such as the modal host.
- Read `docs/agents/testing.md` before changing tests or deciding validation scope.
- Read `docs/agents/workflows.md` before changing workflow primitives, guest persistence, or social onboarding flow shape.
- Read `docs/agents/theme.md` before changing the Phoenix integration or app shell structure.
