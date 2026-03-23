# Agent Guide: Livewire

## Purpose

Read this file before changing `app/Livewire/`, Livewire-backed views under `resources/views/livewire/`, or shared Livewire UI primitives such as the modal host.

## Stable Conventions

### Component Shape

- Livewire 3 is the default interactive layer for authenticated UI.
- Keep components thin. Mutation, validation, and domain branching should converge in actions or services.
- Prefer typed scalar state and small arrays over storing models directly in mutable public properties.
- Use computed properties when a value is derived from current state or relationships rather than passing everything through `render()`.

### Form State

- Forms that mutate data should prefer a `$fields` array for editable form state.
- Render validation errors inline near the relevant field, with a top-level alert only for non-field failures.
- Reuse the same backend mutation path for UI and non-UI entry points when a workflow already exists.

### Security And Boundaries

- Treat public properties as user-editable input unless they are explicitly locked or derived server-side.
- Do not use mutable public properties to choose arbitrary component names, includes, or privileged query paths.
- When a component receives an identifier, reload the domain record through the current team-scoped relationship or action path rather than trusting the raw value.

### Shared Modal Host

- The shared Livewire modal host lives at `app/Livewire/Modal.php` with the view at `resources/views/livewire/modal.blade.php`.
- Use it when a modal needs to mount another Livewire component or include a Blade partial on demand.
- Configure the child component or template server-side when rendering the modal host; only pass record data at open time.
- The standard open/load/close browser events are:
  - `twitch-modal-load`
  - `twitch-modal-open`
  - `twitch-modal-close`
- Event payloads should always include an `id` matching the modal host `elementId`.
- Use `twitch-modal-load` when opening or reloading the host with record-specific payload data that should remount the child component.
- Use `twitch-modal-open` when reopening a configured child without needing a new payload.
- For dynamic payloads, prefer `data` as the nested modal payload object. The host also forwards `modalId` and `modalData` to the child.

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
$this->dispatch('twitch-modal-load',
    id: 'task-edit-modal',
    data: ['taskId' => $task->id],
);
```

## Verification Pointers

- Add focused Livewire tests for component event handling and rendered state when introducing or changing modal-hosted components.
- When a shared Livewire primitive changes, verify at least one real browser interaction path in `tests/Browser/` if the behavior depends on focus, dismissal, or lifecycle timing.

## When To Update This File

Update this file when the repo’s stable Livewire conventions or shared Livewire primitives change materially.
