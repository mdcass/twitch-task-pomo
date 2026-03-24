<form wire:submit="submit" class="d-flex flex-column gap-4">
    <p class="mb-0">
        {{ __('Add a built-in placeholder widget to the canvas. These use the product’s preview shapes until the runtime widgets are fully wired.') }}
    </p>

    <div class="d-flex flex-column gap-3">
        @foreach ($widgetTypes as $widgetType)
            <label class="border rounded-3 p-3 d-flex align-items-start gap-3 cursor-pointer">
                <input type="radio" class="form-check-input mt-1" name="built-in-widget-type"
                    value="{{ $widgetType->value }}" wire:model.live="fields.type">
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $widgetType->label() }}</div>
                    <div class="small text-body-secondary">
                        {{ $widgetType === \App\Enums\Models\WidgetType::TaskList
                            ? __('Task queue placeholder with grouped pending and completed rows.')
                            : __('Pomodoro timer placeholder with a focus-session preview.') }}
                    </div>
                </div>
            </label>
        @endforeach

        <x-input-error for="fields.type" class="mt-2" />
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-phoenix-secondary" wire:click="cancel">
            {{ __('Cancel') }}
        </button>

        <button type="submit" class="btn btn-primary">
            {{ __('Add Widget') }}
        </button>
    </div>
</form>
