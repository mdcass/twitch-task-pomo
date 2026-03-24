<form wire:submit="submit" class="d-flex flex-column gap-4">
    <p>{{ $mode === 'create'
        ? __('Create a new canvas and choose the output dimensions for your overlay scene.')
        : __('Update the canvas name or dimensions. Existing widget layouts will rescale to fit the new canvas.') }}
    </p>

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="canvas-name">{{ __('Name') }}</label>
            <input id="canvas-name" x-ref="name" type="text"
                class="form-control @error('fields.name') is-invalid @enderror" wire:model.live="fields.name"
                maxlength="255" placeholder="{{ __('Main Stream Canvas') }}">
            <x-input-error for="fields.name" class="mt-2" />
        </div>

        <div class="col-sm-6">
            <label class="form-label" for="canvas-width">{{ __('Width') }}</label>
            <input id="canvas-width" type="number" min="1"
                class="form-control @error('fields.width') is-invalid @enderror" wire:model.live="fields.width">
            <x-input-error for="fields.width" class="mt-2" />
        </div>

        <div class="col-sm-6">
            <label class="form-label" for="canvas-height">{{ __('Height') }}</label>
            <input id="canvas-height" type="number" min="1"
                class="form-control @error('fields.height') is-invalid @enderror" wire:model.live="fields.height">
            <x-input-error for="fields.height" class="mt-2" />
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-phoenix-secondary" wire:click="cancel">
            {{ __('Cancel') }}
        </button>

        <button type="submit" class="btn btn-primary">
            {{ $mode === 'create' ? __('Create Canvas') : __('Save Changes') }}
        </button>
    </div>
</form>
