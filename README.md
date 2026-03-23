# Twitch Task Pomo

## Local Setup

For a new local checkout:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
```

Start the app in development:

```bash
composer dev
```

Run the default quality gate before handing work off:

```bash
php artisan test --parallel
./vendor/bin/pint --test
```

## Project

This repository is a Laravel-first streaming overlay application for Twitch productivity and co-working creators. The near-term product focus is `Overlay Composer`: a single browser-source overlay for OBS that combines Pomodoro and task-list widgets into one secure, team-scoped canvas.

The codebase is still in early foundation work. Current implementation and immediate priorities are tracked in [`docs/RUNBOOK.md`](docs/RUNBOOK.md).

## Documentation

- [`docs/PRD.md`](docs/PRD.md): product requirements and phase scope
- [`docs/RUNBOOK.md`](docs/RUNBOOK.md): current project state at `HEAD`
- [`docs/agents/overview.md`](docs/agents/overview.md): agent-facing project guide and documentation index
- [`docs/agents/theme.md`](docs/agents/theme.md): Phoenix integration boundaries and upstream reference usage
- [`docs/backlog/`](docs/backlog): numbered implementation deep dives

## Phoenix Theme Reference

The purchased Phoenix package is committed as a read-only reference snapshot at `resources/third-party/themes/phoenix-v1.24.0`.

- Do not import from the Phoenix `public/assets` output directly into the app runtime. Product-owned assets live under `resources/css/phoenix` and `resources/js/phoenix`.
- Treat `resources/third-party/themes/phoenix-v1.24.0` as upstream reference material for layouts, widgets, and source SCSS/JS only.
- In PhpStorm or IntelliJ, mark `resources/third-party/themes/phoenix-v1.24.0` as `Excluded` so indexing, symbol search, code completion, and global find-in-files do not prefer upstream theme files over product code. If needed: right-click the directory, then `Mark Directory As` -> `Excluded`.
- If you still want easy browsing in JetBrains IDEs, keep the directory excluded and open specific upstream files from the Project view when needed instead of re-enabling full indexing.
- When searching for implementation files, prefer scopes that exclude `resources/third-party/` so results stay focused on app-owned code.
- Review `docs/agents/theme.md` before changing the Phoenix integration. It defines the runtime ownership boundary and how to use the upstream snapshot safely.

## Working Notes

- Team is the default ownership boundary.
- Blade and Livewire are the default interactive stack.
- Realtime overlay updates should use Laravel Echo.
- Do not add or keep stale planning docs outside the maintained docs structure above.
