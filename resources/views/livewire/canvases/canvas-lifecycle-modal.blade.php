<div class="d-flex flex-column gap-4">
    <div class="d-flex gap-3">
        <div @class([
            'rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0',
            'bg-warning-subtle text-warning-emphasis' => $action === 'restore',
            'bg-danger-subtle text-danger' => $action !== 'restore',
        ]) style="width: 3rem; height: 3rem;">
            <span @class([
                'fas',
                'fa-box-open' => $action === 'restore',
                'fa-box-archive' => $action !== 'restore',
            ]) aria-hidden="true"></span>
        </div>

        <div>
            <p class="mb-2 fw-semibold text-body-emphasis">
                {{ $action === 'restore' ? __('Restore this canvas?') : __('Archive this canvas?') }}
            </p>
            <p class="mb-0 text-body-secondary">
                {{ $action === 'restore'
                    ? __(':name will return to the active canvas list and can be edited again.', ['name' => $this->canvas->name])
                    : __(':name will move out of the active workspace until you restore it from the canvas list.', ['name' => $this->canvas->name]) }}
            </p>
        </div>
    </div>

    <div class="rounded-3 border border-translucent bg-body-highlight px-3 py-2">
        <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Canvas') }}</div>
        <div class="fw-semibold text-body-emphasis">{{ $this->canvas->name }}</div>
        <div class="small text-body-secondary">{{ $this->canvas->width }} x {{ $this->canvas->height }}</div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-phoenix-secondary" wire:click="cancel">
            {{ __('Cancel') }}
        </button>

        <button type="button" @class([
            'btn',
            'btn-warning' => $action === 'restore',
            'btn-danger' => $action !== 'restore',
        ]) wire:click="confirm">
            {{ $action === 'restore' ? __('Restore Canvas') : __('Archive Canvas') }}
        </button>
    </div>
</div>
