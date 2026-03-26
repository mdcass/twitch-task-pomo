<form wire:submit="submit" class="d-flex flex-column gap-3">
    <div>
        <label class="form-label" for="widget-create-type">{{ __('Widget type') }}</label>
        <select id="widget-create-type" class="form-select" wire:model="fields.type">
            @foreach ($widgetTypes as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </select>
        @error('fields.type')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label class="form-label" for="widget-create-name">{{ __('Name') }}</label>
        <input id="widget-create-name" type="text" class="form-control" wire:model="fields.name">
        <div class="form-text">{{ __('Leave blank to use the default name for this widget type.') }}</div>
        @error('fields.name')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary">{{ __('Create Widget') }}</button>
    </div>
</form>
