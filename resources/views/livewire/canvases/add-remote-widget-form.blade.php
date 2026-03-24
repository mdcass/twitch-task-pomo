<form wire:submit="submit" class="d-flex flex-column gap-4">
    <p class="mb-0">
        {{ __('Paste an HTTPS URL that exposes an iframe-safe widget. The editor stores blocked or uncertain previews and shows a visible failure state when the widget cannot render.') }}
    </p>

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="remote-widget-name">{{ __('Name') }}</label>
            <input id="remote-widget-name" x-ref="name" type="text"
                class="form-control @error('fields.name') is-invalid @enderror" wire:model.live="fields.name"
                maxlength="255" placeholder="{{ __('Optional label') }}">
            <x-input-error for="fields.name" class="mt-2" />
        </div>

        <div class="col-12">
            <label class="form-label" for="remote-widget-url">{{ __('Embed URL') }}</label>
            <input id="remote-widget-url" x-ref="url" type="url"
                class="form-control @error('fields.embed_url') is-invalid @enderror" wire:model.live="fields.embed_url"
                placeholder="https://example.com/widget">
            <x-input-error for="fields.embed_url" class="mt-2" />
        </div>
    </div>

    <div class="rounded-3 border border-translucent bg-body-highlight p-3 small text-body-secondary">
        <div class="fw-semibold text-body-emphasis mb-1">{{ __('Embed rules') }}</div>
        <div>
            {{ __('Only HTTPS URLs are accepted. The app checks common iframe-blocking headers, but browser-side failures can still happen and will surface on the canvas.') }}
        </div>
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
