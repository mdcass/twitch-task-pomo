<div class="row g-3">
    <div class="col-12">
        <label class="form-label">{{ __('Title') }}</label>
        <input type="text" class="form-control" wire:model="fields.config.title">
    </div>

    <div class="col-md-6">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="spotify-show-album-art"
                wire:model="fields.config.show_album_art">
            <label class="form-check-label" for="spotify-show-album-art">
                {{ __('Show album art') }}
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('Accent') }}</label>
        <input type="text" class="form-control" wire:model="fields.appearance.accent">
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('Title alignment') }}</label>
        <select class="form-select" wire:model="fields.appearance.title_alignment">
            <option value="left">{{ __('Left') }}</option>
            <option value="center">{{ __('Center') }}</option>
        </select>
    </div>
</div>
