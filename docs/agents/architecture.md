# Agent Guide: Architecture

## Purpose

Read this file before changing tenancy boundaries, authorization, model behavior, persistence ownership, or signed and realtime access patterns.

## Source Of Truth

- Code and tests are the final source of truth.
- `docs/RUNBOOK.md` describes what is currently implemented at `HEAD`.
- `docs/PRD.md` defines product requirements that may expand these boundaries later.

## Stable Conventions And Boundaries

### Team Ownership

- Team is the default tenancy and ownership boundary.
- Most team-scoped domain entities should include `team_id`.
- User-authored records should generally include `created_by_user_id`.
- Canvases, widgets, tasks, Twitch integrations, and later billing concerns should root naturally to Team.

### Relationship-Rooted Writes

- Prefer writes through authorized top-level relationships.
- For team-scoped resources, prefer `user->currentTeam->relation()->create(...)` over detached global creates.
- Put shared team-resolution logic on the model layer instead of repeating it across actions, jobs, and components.

### Model-Centric Domain Logic

- Put model-rooted behavior on models or model concerns when the logic depends on model state and relationships.
- Keep controllers, jobs, commands, and Livewire components thin.
- Avoid spreading core branching logic across multiple entry points.

### Authorization Boundaries

- Enforce privileged operations with policies and gates rather than inline role checks.
- Keep team-aware authorization decisions centralized.
- When access rules change, update both allow and deny-path tests.

### Validation Source Of Truth

- Persisted model validation should live in the action or service that performs the write.
- Controllers, jobs, commands, and Livewire components should reuse that shared validation path rather than duplicating rules.

### Activity Logging And Sensitive Data

- Use Spatie activity logging for meaningful lifecycle changes.
- Activity event names should be enum-backed when activity enums exist for the domain.
- Prefer model-level lifecycle logging plus explicit activity calls for application events.
- Exclude secrets, tokens, signed URL material, and decrypted private values from logs and change sets.

### Realtime And Signed Access

- Realtime updates should use Laravel Echo and Livewire's Echo support.
- Overlay and other public-facing runtime access should assume signed or time-scoped URLs.
- Public identifiers such as `canvas_id` should use UUIDs.
- Team-scoped overlays must remain secure when rendered inside OBS browser sources or other embedded clients.

## Key File Families

- `app/Models/`: tenancy roots and domain relationships
- `app/Policies/`: authorization boundaries
- `app/Actions/`: write paths and shared validation
- `app/Events/`: application events
- `app/Workflows/`: resumable state machines where workflow-backed behavior is appropriate

## Verification Pointers

- Add focused tests for changed actions, policies, and signed-access flows.
- When tenancy boundaries move, assert the affected records resolve under the correct team and author.
- When secrets or tokens are introduced, confirm they are not logged or exposed in activity payloads.

## When To Update This File

Update this file when stable domain conventions or security boundaries change, not when only the current implementation state changes.
