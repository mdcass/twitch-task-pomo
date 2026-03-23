# Workflow Guide

## Purpose

Read this file before changing guest onboarding, workflow persistence, or Livewire flows that need resumable state.

## Source Of Truth

- Code and tests are the source of truth.
- `docs/RUNBOOK.md` owns the current implementation state at `HEAD`.
- This file owns stable workflow conventions, key entrypoints, and update boundaries for agents.

## Core Workflow Files

- Contract and status:
  - `app/Contracts/Workflow.php`
  - `app/Enums/Models/WorkflowStatus.php`
- Core implementation:
  - `app/Workflows/BaseWorkflow.php`
  - `app/Workflows/ArrayWorkflow.php`
  - `app/Exceptions/WorkflowTransitionException.php`
  - `app/Workflows/GuardContract.php`
  - `app/Workflows/GuardResult.php`
- Persistence:
  - `app/Models/WorkflowStore.php`
  - `database/migrations/2026_03_22_000003_create_workflow_stores_table.php`
  - `database/factories/WorkflowStoreFactory.php`
- Livewire bridge:
  - `app/Livewire/Workflows/Concerns/LivewireWorkflow.php`

## Persistence Rules

- Team-rooted workflows persist through `workflow_stores` with `team_id` and `created_by_user_id`.
- Guest workflows may persist directly through `saveStore(useSession: true)`.
- Session-backed guest flows use the session key `workflow_store_id.<workflow class>`.
- Do not invent separate onboarding persistence tables when the workflow store can hold the state.
- If a Livewire flow serializes an `ArrayWorkflow`, preserve the backing store identity across hydration so subsequent requests update the same store.

## Livewire Rules

- A Livewire component is not automatically the workflow.
- Prefer a dedicated workflow class for the state machine and let the component load it on mount or per request.
- Keep form state in `$fields`.
- Use workflow helpers such as `getContextValue()` or workflow-specific convenience methods instead of reaching into raw `records`, except in tests that explicitly verify persistence shape.

## Social Onboarding Entry Points

If a task changes social onboarding or guest workflow handoff behavior, start with these files:

- `app/Workflows/Auth/SocialAuthHandshakeWorkflow.php`
- `app/Workflows/Auth/SocialRegistrationWorkflow.php`
- `app/Actions/Auth/SocialAuthService.php`
- `app/Livewire/Auth/SocialRegistrationEmailForm.php`
- `resources/views/livewire/auth/social-registration-email-form.blade.php`

## User Creation Boundary

- `app/Actions/Fortify/CreateNewUser.php` is the only action that should create `users` rows.
- Auth-specific actions may validate their own payloads and perform surrounding work, but they should delegate user creation to `CreateNewUser`.
- Both standard and social registration paths should provision the default streamer team through that shared user-creation action.

## Primary Tests

- `tests/Feature/Workflows/BaseWorkflowTest.php`
- `tests/Feature/Workflows/ArrayWorkflowTest.php`
- `tests/Feature/Workflows/LivewireWorkflowConcernTest.php`
- `tests/Feature/SocialAuthenticationTest.php`
- `tests/Feature/SocialRegistrationEmailFormTest.php`

## When To Update This File

Update this file when:

- workflow primitives move,
- guest or session workflow conventions change,
- the canonical social onboarding entrypoints or handoff boundaries change,
- the user-creation boundary for workflow-backed onboarding changes.
