<div class="row g-3">
    <div class="col-12">
        <label class="form-label">{{ __('Title') }}</label>
        <input type="text" class="form-control" wire:model="fields.config.title">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('Goal target') }}</label>
        <input type="number" min="1" class="form-control" wire:model="fields.config.goal_target">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('End date') }}</label>
        <input type="date" class="form-control" wire:model="fields.config.end_date">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('Sound preset') }}</label>
        <select class="form-select" wire:model="fields.config.sound_preset">
            <option value="none">{{ __('None') }}</option>
            <option value="chime">{{ __('Chime') }}</option>
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
