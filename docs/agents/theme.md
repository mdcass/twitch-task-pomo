# Phoenix Theme Integration

This project adopts the purchased Phoenix Bootstrap 5 theme to satisfy the Phase 1 PRD work for `BP-TASK-P1-04`, `BP-TASK-P1-05`, and the shared UI foundation needed by `BP-TASK-P1-06`.

## Source Of Truth

- Product requirements remain in [docs/PRD.md](/Users/mike/Projects/twitch-task-pomo/docs/PRD.md).
- The committed upstream Phoenix snapshot lives at [resources/third-party/themes/phoenix-v1.24.0](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0).
- That snapshot is reference-only. Do not point runtime Blade layouts or Vite entrypoints at Phoenix `public/assets` output.
- Product-owned runtime assets stay under `resources/css`, `resources/js`, and `resources/views`.

## Why Phoenix Was Adopted

- The PRD explicitly requires replacing the initial Tailwind-heavy Jetstream shell with the purchased Bootstrap 5 theme during Phase 1 so the application does not build further UI debt on the wrong primitives.
- Phoenix provides a strong Bootstrap-first visual system, auth patterns, dashboard shell references, and component styling while still allowing the application to keep Blade and JavaScript ownership local.

## Where To Look In Upstream Phoenix

- Layout references: [resources/third-party/themes/phoenix-v1.24.0/public/demo](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0/public/demo)
- Starter/dashboard shell references: [resources/third-party/themes/phoenix-v1.24.0/public/pages/starter.html](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0/public/pages/starter.html), [resources/third-party/themes/phoenix-v1.24.0/public/index.html](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0/public/index.html)
- Auth references: [resources/third-party/themes/phoenix-v1.24.0/public/pages/authentication](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0/public/pages/authentication)
- Widgets and components: [resources/third-party/themes/phoenix-v1.24.0/public/widgets.html](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0/public/widgets.html), [resources/third-party/themes/phoenix-v1.24.0/public/modules/components](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0/public/modules/components)
- SCSS source: [resources/third-party/themes/phoenix-v1.24.0/src/scss](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0/src/scss)
- JS source: [resources/third-party/themes/phoenix-v1.24.0/src/js](/Users/mike/Projects/twitch-task-pomo/resources/third-party/themes/phoenix-v1.24.0/src/js)

## Dependency Policy

- Import the full upstream Phoenix SCSS theme into the product-owned Vite entrypoint, then layer a small local shell stylesheet on top.
- Keep supplemental JavaScript minimal and selective.
- Only install dependencies required by adopted runtime behavior.
- Do not import Phoenix charts, editors, maps, calendars, or large vendor bundles until a product feature needs them.

## Adoption Map

| Upstream reference | Local destination | Status | Why |
| --- | --- | --- | --- |
| `public/pages/authentication/simple/sign-in.html` | login screen under `resources/views/auth/login.blade.php` and the `simple` variant of `resources/views/layouts/guest.blade.php` | Adopted and adapted | The login page now tracks the centered Phoenix simple auth pattern with product-owned Blade markup and local auth behavior preserved. |
| `public/pages/authentication/card/sign-up.html` | reusable auth card shell under `resources/views/components/auth/page-card.blade.php` and the `card` variant of `resources/views/layouts/guest.blade.php` | Prepared and adapted | The future registration redesign can plug into a product-owned Phoenix-style card structure without depending on upstream built assets. |
| `src/scss/theme.scss`, `src/scss/_bootstrap.scss`, `src/scss/theme/_theme.scss` | `resources/css/phoenix/_theme.scss` and `resources/css/phoenix/app.scss` | Adopted | The runtime now imports the full upstream Phoenix SCSS surface through a local wrapper that preserves Phoenix load order while resolving dependencies from the repo root. |
| local shell polish layered after `theme.scss` | `resources/css/phoenix/_shell.scss` | Adopted minimally | Only product-specific branding, welcome-card styling, and modal behavior remain local. Authenticated shell structure and spacing should prefer Phoenix and Bootstrap classes directly. |
| `public/documentation/layouts/vertical-navbar.html`, `public/documentation/layouts/horizontal-navbar.html`, `public/demo/combo-nav.html`, `public/demo/dual-nav.html`, `public/demo/topnav-slim.html` | authenticated shell in `resources/views/layouts/app.blade.php` and `resources/views/components/shell/*` | Adopted and adapted | The authenticated surface now supports multiple Phoenix-style layout variants through one product-owned layout API, a centralized PHP navigation source, and Blade-rendered menu primitives. |
| `src/js/config.js`, `src/js/theme/theme-control.js`, and Phoenix layout navbar interactions | `resources/js/phoenix/app.js` | Adapted | Theme preference behavior remains local, while sidebar collapse and responsive shell controls are implemented as minimal app-owned JS rather than importing the full Phoenix runtime. |
| `public/assets/*` built files | none | Rejected for runtime | Built upstream assets would couple the app to Phoenix Gulp output and duplicate the source-of-truth problem. |
| Phoenix demo widgets, support chat, notifications, search UI, and feature dashboards | none | Deferred or rejected | These are not needed for the current Phase 1 auth/shared-shell slice and should not be pulled into the authenticated shell without a product requirement. |

## Authenticated Layout Rules

- `x-app-layout` is the single authenticated shell entrypoint and accepts `vertical`, `horizontal`, `combo`, `dual-nav`, or `topnav-slim`.
- `vertical` is the default authenticated layout for current application pages.
- Primary navigation definitions live in PHP under `app/Support/Shell` and should be rendered through the shell components under `resources/views/components/shell`.
- Shared authenticated shell state is resolved in PHP and rendered through the product-owned Blade shell components under `resources/views/components/shell`.
- Keep the shared utility area limited to brand, team/account controls, and theme switching unless a product requirement adds more.
- Do not reintroduce Phoenix demo search, notifications, settings panels, or placeholders into the app shell.

## Rules For Future Agents

- Use the upstream snapshot to discover structure, classes, and visual intent.
- Prefer translating Phoenix patterns into product-owned Blade components and minimal local JavaScript.
- Keep the adoption map current when a new Phoenix source file materially influences the app.
- If a backlog note is created during theme work, fold durable conclusions back into this file or remove the note before the task completes.
