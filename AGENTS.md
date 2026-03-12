This repository is a Laravel-first streaming overlay application for Twitch productivity and co-working creators.

# Structure

Primary code locations:

- `app/` for domain and application logic.
- `database/` for migrations, factories, and seeders.
- `resources/views/` for Blade and Livewire UI.
- `resources/js/` and `resources/css/` for frontend assets.
- `routes/` for HTTP and console entry points.
- `docs/` for product and planning documentation.
- `tests/` for feature and unit coverage.

Do not modify files in `vendor/`.

# Working Rules

1. Keep changes task-scoped and minimal.
2. Do not change coding style or formatting outside edited code unless the task requires it.
3. Do not modify config or build files unless the task requires it.
4. Do not commit unless explicitly asked.
5. Ignore unrelated local changes and do not clean up work you did not author.
6. Re-check logic and tests after editing when appropriate.
7. Keep product requirements in `docs/PRD.md` and implementation conventions in this file unless a separate planning document is requested.

# Documentation Hygiene

- `docs/PRD.md` is the product source of truth for requirements and phase scope.
- `docs/RUNBOOK.md` is the main implementation runbook and should describe the state of the project at `HEAD`.
- Additional planning or execution documents must live in `docs/backlog/` as numbered files such as `001-concrete-schema.md`, `002-phase-1-slice.md`, and so on.
- Backlog documents should extend the PRD and runbook, not restate them. Link to canonical sections instead of copying requirement text.
- Tasks that create temporary planning notes should either fold the durable outcome back into `docs/RUNBOOK.md` or `docs/PRD.md`, or remove the temporary note before the task is complete.
- When a backlog document is no longer current or its decisions have been absorbed into the codebase and runbook, update or delete it in the same task rather than leaving stale documentation behind.

# Core Domain Conventions

## Team Ownership

- Team is the default tenancy and ownership boundary.
- Most domain entities should include `team_id`.
- User-authored records should generally include `created_by_user_id`.
- Canvases, widgets, tasks, Twitch integrations, and later billing concerns should root naturally to Team.

## Relationship-Rooted Writes

- Prefer writes through authorized top-level relationships.
- For team-scoped resources, prefer `user->currentTeam->relation()->create(...)` over detached global creates.
- Put shared team-resolution logic on the model layer instead of repeating it across actions and components.

## Model-Centric Domain Logic

- Put model-rooted behavior on models or model concerns when the logic depends on model state and relationships.
- Keep controllers, jobs, commands, and Livewire components thin.
- Avoid spreading core branching logic across multiple entry points.

## Authorization Boundaries

- Enforce privileged operations with policies and gates rather than inline role checks.
- Keep team-aware authorization decisions centralized.

## Activity Logging

- Use Spatie activity logging for meaningful lifecycle changes.
- Activity event names should be enum-backed.
- Prefer model-level lifecycle logging plus explicit activity calls for application events.
- Exclude secrets, tokens, and other sensitive fields from change logs.

## Validation Source of Truth

- Persisted model validation should live in the action or service that performs the write.
- Commands, controllers, jobs, and Livewire components should reuse that shared validation path rather than duplicating rules.

## Sensitive Data Handling

- Never log plaintext secrets, OAuth tokens, signed URL material, or decrypted private values.
- If encrypted casts are introduced, provide masked defaults and explicit raw accessors only where intentionally required.

# Realtime and Overlay Conventions

- Realtime updates should use Laravel Echo and Livewire's Echo support.
- Overlay access should assume signed or time-scoped URLs.
- Public identifiers such as `canvas_id` should use UUIDs.
- Team-scoped overlays must remain secure even when rendered inside OBS browser sources.

# Frontend Conventions

- Blade and Livewire are the default interactive stack.
- Keep supplemental JavaScript minimal.
- Only add new frontend libraries when requirements cannot be met cleanly with the existing Laravel, Livewire, Vite, and browser primitives.
- Do not lock major library choices into planning docs prematurely.

# Livewire Expectations

- Livewire forms that mutate data should use real form submissions. Form data should be in a $fields property on a component, not top level properties.
- Validation errors should render inline near fields, with non-field errors in a top-level alert when needed.
- Shared backend mutation paths should be reused across UI and non-UI entry points.
- Computed properties should be used over passing data through the render method to the view

# Testing and Validation

Default quality gate for code changes:

1. `php artisan test --parallel`
2. `./vendor/bin/pint --test`

Testing rules:

- Prefer factories for model setup.
- Add focused tests for behavior being changed.
- Validate policy and security boundaries when introducing privileged or signed-access flows.
