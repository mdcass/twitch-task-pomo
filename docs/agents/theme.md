# Phoenix Theme Integration

## Purpose

Read this file before changing the authenticated shell, guest auth layouts, or the boundary between product-owned UI and upstream Phoenix source material.

## Source Of Truth

- Product requirements remain in [../PRD.md](../PRD.md).
- The current implementation state lives in [../RUNBOOK.md](../RUNBOOK.md).
- The committed upstream Phoenix snapshot lives at [../../resources/third-party/themes/phoenix-v1.24.0](../../resources/third-party/themes/phoenix-v1.24.0).

## Runtime Ownership Boundaries

- The Phoenix snapshot is reference-only.
- Do not point runtime Blade layouts or Vite entrypoints at Phoenix `public/assets` output.
- Product-owned runtime assets stay under `resources/css`, `resources/js`, and `resources/views`.
- If a Phoenix pattern is adopted, translate it into local Blade, SCSS, and JavaScript rather than importing built upstream output.

## Where To Look In Upstream Phoenix

- Layout references: `resources/third-party/themes/phoenix-v1.24.0/public/demo`
- Starter and dashboard references: `resources/third-party/themes/phoenix-v1.24.0/public/pages/starter.html`, `resources/third-party/themes/phoenix-v1.24.0/public/index.html`
- Auth references: `resources/third-party/themes/phoenix-v1.24.0/public/pages/authentication`
- Component references: `resources/third-party/themes/phoenix-v1.24.0/public/modules/components`
- SCSS source: `resources/third-party/themes/phoenix-v1.24.0/src/scss`
- JS source: `resources/third-party/themes/phoenix-v1.24.0/src/js`

## Dependency And Adoption Policy

- Import Phoenix styling through the local wrappers in `resources/css/phoenix/`.
- Keep supplemental JavaScript minimal and selective.
- Only install dependencies required by adopted runtime behavior.
- Do not import Phoenix charts, editors, maps, calendars, or other large vendor bundles until a product feature requires them.
- When importing Phoenix components into the product, do not introduce custom CSS where we already have working CSS imported by Phoenix

## Layout And Shell Rules

- `x-app-layout` is the single authenticated shell entrypoint.
- Primary navigation definitions live in PHP under `app/Support/Shell` and should render through `resources/views/components/shell/`.
- Shared authenticated shell state should stay product-owned and Blade-rendered.
- Keep the shared utility area limited to product needs such as brand, team or account controls, and theme switching unless a requirement expands it.
- Do not reintroduce Phoenix demo search, notifications, settings panels, support chat, or placeholder dashboards without a product requirement.

## Verification Pointers

- When changing shell structure or auth layouts, confirm runtime files still live under product-owned paths.
- Update `docs/RUNBOOK.md` when the implemented Phoenix adoption state changes materially.
- Run the most relevant layout or auth feature tests when shell behavior changes.

## When To Update This File

Update this file when the stable integration boundary with Phoenix changes, not when only the current adopted surface changes.
