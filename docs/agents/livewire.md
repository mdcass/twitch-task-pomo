# Agent Guide: Livewire

## Purpose

Read this file before changing `app/Livewire/`, Livewire-backed views under `resources/views/livewire/`, or shared Livewire UI primitives such as the modal host.

## Stable Conventions

### Component Shape

- Livewire 3 is the default interactive layer for authenticated UI.
- Livewire should be mounted inside Blade app-shell views for authenticated product pages rather than used as a full-page route target.
- Treat app-shell controllers as the routing and shell boundary; treat Livewire components as the page-behavior boundary.
- Keep components thin. Aggregate-local mutations should call model or model-concern entrypoints, while validated commands and cross-aggregate or external orchestration should converge in actions or services.
- Prefer typed scalar state and small arrays over storing models directly in mutable public properties.
- Use computed properties when a value is derived from current state or relationships rather than passing everything through `render()`.

### Form State

- Forms that mutate data should prefer a single public `$fields` array on the component for editable form state.
- Keep component `rules()` aligned with `fields.*` keys so Livewire can persist and render validation errors natively without manual remapping.
- When validating a `$fields` bag, define `validationAttributes()` so user-facing error messages use human labels like `name` instead of raw keys like `fields.name`.
- Inline validation errors for editable fields should target `fields.*` paths, with a top-level alert only for non-field failures.
- Keep reusable validation close to the action family when multiple actions share the same persisted payload shape, but avoid introducing generic validation frameworks unless the repo clearly needs them.
- Reuse the same backend mutation path for UI and non-UI entry points when a workflow already exists.

### Security And Boundaries

- Treat public properties as user-editable input unless they are explicitly locked or derived server-side.
- Do not use mutable public properties to choose arbitrary component names, includes, or privileged query paths.
- When a component receives an identifier, reload the domain record through the current team-scoped relationship or action path rather than trusting the raw value.

### Shared Modal Host

- The shared Livewire modal host lives at `app/Livewire/Modal.php` with the view at `resources/views/livewire/modal.blade.php`.
- Use it when a modal needs to mount another Livewire component or include a Blade partial on demand.
- Configure the child component or template server-side when rendering the modal host; only pass record data at open time.
- The modal shell is Bootstrap-backed for lifecycle behavior. Let Bootstrap handle showing, hiding, backdrop timing, and focus trapping; keep custom JS limited to Livewire state sync and initial/fallback focus handoff.
- The standard open/load/close browser events are:
  - `overlay-modal-load`
  - `overlay-modal-open`
  - `overlay-modal-close`
- Event payloads should always include an `id` matching the modal host `elementId`.
- Use `overlay-modal-load` when opening or reloading the host with record-specific payload data that should remount the child component.
- Use `overlay-modal-open` when reopening a configured child without needing a new payload.
- For dynamic payloads, prefer `data` as the nested modal payload object. The host also forwards `modalId` and `modalData` to the child.
- Prefer the shared `App\Livewire\Concerns\InteractsWithOverlays` trait or `x-overlay-trigger` Blade component over hand-written browser event strings.
- Triggers should reference the host `elementId` directly plus an explicit `surface` rather than routing through a separate alias registry.

### Shared Offcanvas Host

- The shared Livewire offcanvas host lives at `app/Livewire/Offcanvas.php` with the view at `resources/views/livewire/offcanvas.blade.php`.
- Use it when a side panel needs to mount another Livewire component or include a Blade partial on demand.
- Configure the child component or template server-side when rendering the offcanvas host; only pass record data at open time.
- The offcanvas shell is Bootstrap-backed for slide-in, backdrop, and focus lifecycle. Keep custom JS limited to Livewire state sync and focus handoff rather than reimplementing the plugin runtime.
- The standard open/load/close browser events are:
  - `overlay-offcanvas-load`
  - `overlay-offcanvas-open`
  - `overlay-offcanvas-close`
- Event payloads should always include an `id` matching the offcanvas host `elementId`.
- Use `overlay-offcanvas-load` when opening or reloading the host with record-specific payload data that should remount the child component.
- Use `overlay-offcanvas-open` when reopening a configured child without needing a new payload.
- For dynamic payloads, prefer `data` as the nested offcanvas payload object. The host also forwards `offcanvasId` and `offcanvasData` to the child.

### Example Pattern

```blade
@push('modals')
    @livewire('modal', [
        'component' => ['tasks.edit-task-form', ['mode' => 'edit']],
        'elementId' => 'task-edit-modal',
        'title' => 'Edit Task',
        'maxWidth' => 'lg',
    ])
@endpush
```

```php
$this->openOverlay(
    surface: 'modal',
    id: 'task-edit-modal',
    data: ['taskId' => $task->id],
);
```

## Verification Pointers

- Add focused Livewire tests for component event handling and rendered state when introducing or changing modal-hosted components.
- When a shared Livewire primitive changes, verify at least one real browser interaction path in `tests/Browser/` if the behavior depends on focus, dismissal, or lifecycle timing.

## When To Update This File

Update this file when the repo’s stable Livewire conventions or shared Livewire primitives change materially.
