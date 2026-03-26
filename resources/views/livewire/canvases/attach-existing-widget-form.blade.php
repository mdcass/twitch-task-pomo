<form wire:submit="submit" class="d-flex flex-column gap-3">
    <div>
        <label class="form-label" for="attach-widget-id">{{ __('Shared widget') }}</label>
        <select id="attach-widget-id" class="form-select" wire:model="fields.widget_id">
            <option value="">{{ __('Select a widget') }}</option>
            @foreach ($widgets as $widget)
                <option value="{{ $widget->id }}">{{ $widget->displayName() }} · {{ $widget->type->label() }}</option>
            @endforeach
        </select>
        @error('fields.widget_id')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary">{{ __('Attach Widget') }}</button>
    </div>
</form>
