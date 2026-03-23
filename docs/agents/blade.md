# Agent Guide: Blade

## Purpose

Read this file before changing Blade layouts, shared Blade components, or page-composition views that embed Livewire components.

## Stable Conventions

### Composition

- Blade views should primarily compose product-owned layouts, Blade components, and Livewire components.
- Keep controllers and route closures thin; page-level Blade views should mostly choose layout structure and embed the relevant Livewire surface.
- Use `@stack('modals')` for page-level modal and offcanvas hosts that should render near the end of the authenticated shell.

### Shared Components

- Shared shell and UI primitives belong in `resources/views/components/`.
- Shared Livewire component views belong in `resources/views/livewire/`.
- Prefer existing shared components such as `x-app-layout`, `x-modal`, shell primitives, and auth/form helpers before introducing a new anonymous component.

### Template Formatting

- Use `@class([...])` for conditional classes instead of hand-built string concatenation.
- When a tag spans multiple attributes, prefer one attribute per line so `blade-formatter` can keep it stable.
- Separate distinct visual sections with blank lines.
- Keep inline PHP light; move branching or normalization into the Blade component class, Livewire component, or view-model source when it starts to carry behavior.

### Livewire Embedding

- Use `@livewire(...)` for page- or section-level components.
- For modal-hosted or offcanvas-hosted dynamic Livewire children, mount the host in `@stack('modals')` and trigger it with the shared browser events instead of inlining one-off shells per table row.
- Prefer one modal host per modal concern on the page. Row actions should dispatch the target record payload into that host rather than rendering duplicate modal markup in each loop iteration.
- When the same view loops over records, key modal triggers and nested Livewire children carefully so rerenders do not cross wires between records.
- Prefer `x-overlay-trigger` for Blade-driven modal and offcanvas opens so event naming and payload shape stay centralized.
- Point `x-overlay-trigger` at the mounted host `elementId` with an explicit `surface`.

### Shared Modal Shell

- `resources/views/components/modal.blade.php` is the shared Bootstrap/Phoenix shell.
- It owns structure, accessibility attributes, focus handling, and dismissibility rules.
- Callers own the header/body/footer content and any destructive icon/body composition.

## Verification Pointers

- Run `blade-formatter --write "resources/views/**/*.blade.php"` after Blade edits.
- When editing shared layouts or shared components, verify the affected feature tests and at least one representative browser path if the change affects interaction or focus.

## When To Update This File

Update this file when the repo’s stable Blade composition rules or shared template patterns change materially.
