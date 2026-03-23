# Agent Guide: Frontend

## Purpose

Read this file before changing Blade layouts, Livewire components, Phoenix-backed UI, or frontend asset ownership.

## Source Of Truth

- Code and tests are the final source of truth.
- `docs/agents/blade.md` covers Blade composition and shared template conventions.
- `docs/agents/livewire.md` covers Livewire component and shared modal-host conventions.
- `docs/agents/theme.md` covers Phoenix-specific integration boundaries.
- `docs/RUNBOOK.md` describes the current shell and auth UI implementation at `HEAD`.

## Stable Conventions And Boundaries

### Blade And Livewire Defaults

- Blade and Livewire are the default interactive stack.
- Keep supplemental JavaScript minimal.
- Only add new frontend libraries when the requirement cannot be met cleanly with Laravel, Livewire, Vite, Bootstrap, and browser primitives already in the repo.

### Livewire Mutation Rules

- Livewire forms that mutate data should use real form submissions.
- Form state should live in a `$fields` property rather than top-level mutable properties.
- Validation errors should render inline near fields, with non-field errors in a top-level alert when needed.
- Shared backend mutation paths should be reused across UI and non-UI entry points.
- Prefer computed properties over passing data through the render method to the view.

### Product-Owned UI Boundaries

- Runtime Blade, CSS, and JavaScript stay product-owned under `resources/views/`, `resources/css/`, and `resources/js/`.
- Use Phoenix as a reference and source material, not as a runtime asset source.
- Do not point runtime layouts or Vite entrypoints at Phoenix `public/assets` output.

### When Phoenix References Are Appropriate

- Use the committed Phoenix snapshot to study layout structure, auth patterns, class combinations, and SCSS or JS source behavior.
- Translate adopted patterns into product-owned Blade components and local assets.
- Keep the shared app shell intentional and product-focused. Do not import Phoenix demo placeholders, dashboards, or unrelated widgets without a product requirement.

## Key File Families

- `resources/views/layouts/`: app and guest document shells
- `resources/views/components/`: shared Blade components, including auth and shell primitives
- `resources/views/livewire/`: Livewire templates
- `app/Livewire/`: Livewire components
- `resources/css/phoenix/` and `resources/js/phoenix/`: local Phoenix wrappers and runtime behavior
- `resources/third-party/themes/phoenix-v1.24.0/`: upstream Phoenix reference snapshot

## Verification Pointers

- Run the most relevant feature tests when changing layouts, auth flows, or Livewire mutations.
- When finishing Blade edits under `resources/views/`, run `blade-formatter --write "resources/views/**/*.blade.php"` so the committed templates match the repository formatter.
- Product-owned Markdown mail views remain formatter-safe through the shared `App\Support\Mail\MarkdownSlot` normalization layer, so keep those templates on the normal formatter path.
- When changing the shared shell or auth pages, confirm the product-owned runtime files still match the intended Phoenix reference without importing upstream built assets.
- Update `docs/RUNBOOK.md` if the implemented shell or auth surface changes materially.

## When To Update This File

Update this file when stable UI conventions or frontend ownership boundaries change.
