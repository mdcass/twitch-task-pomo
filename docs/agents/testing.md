# Agent Guide: Testing

## Purpose

Read this file before changing tests, deciding validation scope, or choosing between focused checks and the default quality gate.

## Source Of Truth

- Tests in `tests/` are the final source of truth for executable behavior.
- `docs/RUNBOOK.md` describes the current implementation areas that need coverage.
- `docs/PRD.md` defines requirements that may need new coverage as implementation expands.

## Default Quality Gate

For code changes, the default validation gate is:

1. `php artisan test --parallel`
2. `./vendor/bin/pint --test`

For documentation-only changes, a test run is not required unless the task also changes executable code.

## Stable Testing Conventions

### Focused Iteration

- Prefer running the smallest relevant test file or filtered subset while iterating.
- Before handing off a code change, run the default quality gate unless the user explicitly scopes validation differently.

### Test Structure

- Use `tests/Feature/` for HTTP, auth, Livewire, workflow, and integration behavior.
- Use `tests/Unit/` for isolated domain logic that does not need a full feature harness.
- Add focused regression tests for the behavior being changed.

### Data Setup

- Prefer Laravel factories for model setup.
- Keep setup minimal and local to the behavior under test.
- Reuse existing helpers and test patterns before introducing new fixtures or abstractions.

### Security And Boundary Coverage

- When changing policies, team scoping, or privileged operations, cover both allowed and denied paths.
- When changing signed or public access flows, assert both valid and invalid access behavior.
- When changing workflow-backed onboarding, cover persistence and resume behavior rather than only the happy path.

## Key File Families

- `tests/Feature/`: primary application behavior coverage
- `tests/Feature/Workflows/`: workflow primitive coverage
- `tests/Unit/`: isolated logic
- `database/factories/`: default model factories

## Verification Pointers

- If you touch auth or onboarding code, start with the smallest relevant auth or workflow feature test, then expand as needed.
- If you touch shared shell or layout behavior, include the relevant feature tests plus manual browser verification when the change is visual.
- If a change affects signed URLs, authorization, or realtime entry boundaries, make sure failure paths are asserted explicitly.

## When To Update This File

Update this file when the repo's default validation gate or stable testing conventions change.
