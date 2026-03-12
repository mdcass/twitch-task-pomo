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
- [`docs/backlog/`](docs/backlog): numbered implementation deep dives

## Working Notes

- Team is the default ownership boundary.
- Blade and Livewire are the default interactive stack.
- Realtime overlay updates should use Laravel Echo.
- Do not add or keep stale planning docs outside the maintained docs structure above.
