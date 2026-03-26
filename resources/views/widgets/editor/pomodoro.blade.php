<div class="row g-3">
    <div class="col-12">
        <label class="form-label">{{ __('Title') }}</label>
        <input type="text" class="form-control" wire:model="fields.config.title">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('Focus minutes') }}</label>
        <input type="number" min="1" max="180" class="form-control" wire:model="fields.config.focus_minutes">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('Break minutes') }}</label>
        <input type="number" min="1" max="60" class="form-control"
            wire:model="fields.config.break_minutes">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('State') }}</label>
        <select class="form-select" wire:model="fields.config.state">
            <option value="focus">{{ __('Focus') }}</option>
            <option value="break">{{ __('Break') }}</option>
            <option value="paused">{{ __('Paused') }}</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('Title alignment') }}</label>
        <select class="form-select" wire:model="fields.appearance.title_alignment">
            <option value="left">{{ __('Left') }}</option>
            <option value="center">{{ __('Center') }}</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('Accent') }}</label>
        <input type="text" class="form-control" wire:model="fields.appearance.accent">
    </div>
</div>
