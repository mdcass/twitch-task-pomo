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
npm run playwright:install
```

For the planned split-origin composer and overlay architecture, link and secure both local Valet hosts against this same project directory before implementing the overlay-origin work:

```bash
cd /Users/mike/Projects/twitch-task-pomo
valet link app.twitch-task-pomo
valet secure app.twitch-task-pomo
valet link overlay.twitch-task-pomo
valet secure overlay.twitch-task-pomo
```

Then keep `.env` aligned with `.env.example`:

- `APP_URL=https://app.twitch-task-pomo.test`
- `APP_OVERLAY_URL=https://overlay.twitch-task-pomo.test`
- provider callback URLs should point at the app origin
- `SESSION_DOMAIN=null` should remain host-only unless the security model is intentionally changed later

Start the app in development:

```bash
composer dev
```

Run the default quality gate before handing work off:

```bash
php artisan test --parallel
./vendor/bin/pint --test
```

Run the Pest browser suite for end-to-end user journeys:

```bash
composer test:browser
```

Useful focused test commands while iterating:

```bash
php artisan test tests/Feature/SocialAuthenticationTest.php
./vendor/bin/pest --configuration=phpunit.browser.xml tests/Browser/AuthBrowserTest.php
```

Testing notes:

- `php artisan test --parallel` remains the main detailed behavior and regression suite.
- `composer test:browser` runs the broader Pest browser journeys in `tests/Browser/`.
- Browser tests stay on localhost and use the testing-only OAuth harness rather than external provider traffic.

## Project

This repository is a Laravel-first streaming overlay application for Twitch productivity and co-working creators. The near-term product focus is `Overlay Composer`: a single browser-source overlay for OBS that combines Pomodoro and task-list widgets into one secure, team-scoped canvas.

The codebase is still in early foundation work. Current implementation and immediate priorities are tracked in [`docs/RUNBOOK.md`](docs/RUNBOOK.md).

## Composer Mental Model

The canvas composer now treats widget geometry as four separate concepts:

- `position`: where the widget frame sits on the canvas
- `frame size`: how large the widget appears on the canvas
- `source bounds`: the authored content box inside the widget
- `crop insets`: how much of that authored content is hidden from each edge

The practical model is:

- Resize changes the `frame size`
- Crop changes only the `crop insets`
- Source-bounds editing changes only the `source bounds`
- Stretch changes the `frame size` without preserving aspect ratio

Current editor gestures are:

- drag handles: resize while preserving the visible-source ratio
- `Alt` / `Option` + drag: crop
- `Shift` + drag: stretch
- `Ctrl` / `Cmd` + drag: edit source bounds

Reset controls above the canvas reverse each manipulation independently:

- `Reset Crop`: clears crop only
- `Reset Source`: restores the widget's stored source-bounds defaults and clears crop
- `Reset Aspect`: resizes the frame back to the current visible-source aspect ratio

## Documentation

- [`docs/PRD.md`](docs/PRD.md): product requirements and phase scope
- [`docs/RUNBOOK.md`](docs/RUNBOOK.md): current project state at `HEAD`
- [`docs/agents/overview.md`](docs/agents/overview.md): agent-facing project guide and documentation index
- [`docs/agents/theme.md`](docs/agents/theme.md): Phoenix integration boundaries and upstream reference usage
- [`docs/backlog/`](docs/backlog): numbered implementation deep dives
- [`docs/backlog/002-overlay-origin-and-widget-preview-architecture.md`](docs/backlog/002-overlay-origin-and-widget-preview-architecture.md): proposed split-origin, iframe, preview, Valet, and deployment spec for the composer hardening pass

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

## Deployment Notes

The planned production shape for signed overlays assumes two HTTPS origins served by the same Laravel codebase:

- app origin, for example `app.example.com`
- overlay origin, for example `overlay.example.com`

The app origin owns authentication, dashboard UI, and provider callbacks. The overlay origin owns signed publishable canvas routes and low-trust preview shells. Keep session cookies host-only by default rather than sharing them across subdomains.

For an Nginx target, plan on:

- separate `server_name` blocks for app and overlay origins
- TLS on both origins
- the same Laravel release and PHP-FPM pool behind both hosts unless operations later require a dedicated split
- origin-aware middleware and headers in Laravel for CSP, signed overlay delivery, and preview-shell framing rules
