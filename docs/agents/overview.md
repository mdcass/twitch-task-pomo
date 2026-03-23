# Agent Guide: Overview

## Purpose

Read this file after `AGENTS.md` to understand the repo shape, stack, and documentation boundaries before making changes.

## Source Of Truth

- Code and tests are the final source of truth.
- `docs/PRD.md` owns product requirements and phase scope.
- `docs/RUNBOOK.md` owns the current implementation state at `HEAD`.
- `docs/agents/*.md` own agent-facing operating guidance and project conventions.

## Project Identity

This repository is a Laravel-first streaming overlay application for Twitch productivity and co-working creators.

The near-term product focus is `Overlay Composer`: a single browser-source overlay for OBS and similar tools that combines Pomodoro and task-list widgets into one secure, team-scoped canvas.

## Stack

- PHP `^8.2`
- Laravel `^12`
- Laravel Jetstream
- Livewire `^3`
- Vite
- Bootstrap 5 with a product-owned Phoenix integration
- Laravel Socialite with Twitch and Discord providers
- Laravel Sanctum
- Spatie activity logging
- PHPUnit `^11`, ParaTest, and Laravel Pint

## Directory Map

- `app/`: domain logic, actions, Livewire components, models, policies, support classes, and workflows
- `database/`: migrations, factories, and seeders
- `resources/views/`: Blade layouts, components, auth views, and Livewire templates
- `resources/css/` and `resources/js/`: product-owned runtime assets, including Phoenix wrappers
- `routes/`: HTTP, API, and console entrypoints
- `docs/`: requirements, runbook, agent guides, and backlog notes
- `tests/`: feature and unit coverage

## Architecture Summary

- Team is the default tenancy and ownership boundary.
- Blade and Livewire are the default interactive stack.
- Controllers, jobs, and components should stay thin; mutation and validation logic should converge in actions or services.
- Workflow primitives are available for resumable onboarding and guest-safe state persistence.
- The authenticated UI is product-owned even when it follows Phoenix references.
- Realtime overlay and viewer-facing experiences should use Laravel Echo and secure URL boundaries.

## Commands And Validation

Use these as the default entrypoints while working:

```bash
composer dev
php artisan test --parallel
./vendor/bin/pint --test
```

For focused work, prefer running the smallest relevant test file or test subset before falling back to the full gate.

## Documentation Index

- [overview.md](./overview.md): project shape, stack, and documentation boundaries
- [architecture.md](./architecture.md): tenancy, authorization, model, logging, and security conventions
- [frontend.md](./frontend.md): Blade, Livewire, Phoenix, and frontend asset ownership rules
- [testing.md](./testing.md): test expectations and validation scope
- [workflows.md](./workflows.md): workflow primitives and social onboarding conventions
- [theme.md](./theme.md): Phoenix integration boundaries and upstream reference usage
- [../RUNBOOK.md](../RUNBOOK.md): current implementation state at `HEAD`
- [../PRD.md](../PRD.md): product requirements and phase scope

## When To Update This File

Update this file when the project stack, top-level directory structure, or documentation boundaries change materially.
