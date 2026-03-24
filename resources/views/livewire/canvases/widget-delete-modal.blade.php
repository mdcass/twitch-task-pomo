<div class="d-flex flex-column gap-4">
    <div class="d-flex gap-3">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0 bg-danger-subtle text-danger"
            style="width: 3rem; height: 3rem;">
            <span class="fas fa-trash" aria-hidden="true"></span>
        </div>

        <div>
            <p class="mb-2 fw-semibold text-body-emphasis">
                {{ __('Delete this widget?') }}
            </p>
            <p class="mb-0 text-body-secondary">
                {{ __(
                    ':name will be permanently removed from this canvas. Undo will not restore deleted widgets in this version.',
                    [
                        'name' => $this->widget->displayName(),
                    ],
                ) }}
            </p>
        </div>
    </div>

    <div class="rounded-3 border border-translucent bg-body-highlight px-3 py-2">
        <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Widget') }}</div>
        <div class="fw-semibold text-body-emphasis">{{ $this->widget->displayName() }}</div>
        <div class="small text-body-secondary">
            {{ __('Canvas: :name', ['name' => $this->widget->canvas->name]) }}
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-phoenix-secondary" data-widget-delete-cancel wire:click="cancel">
            {{ __('Cancel') }}
        </button>

        <button type="button" class="btn btn-danger" data-widget-delete-confirm wire:click="confirm">
            {{ __('Delete Widget') }}
        </button>
    </div>
</div>
