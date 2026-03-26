<div class="row g-3">
    <div class="col-12">
        <label class="form-label">{{ __('Title') }}</label>
        <input type="text" class="form-control" wire:model="fields.config.title">
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('Pending items') }}</label>
        <textarea class="form-control" rows="4" wire:model="fields.config.pending_text"></textarea>
        <div class="form-text">{{ __('One item per line.') }}</div>
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('Completed items') }}</label>
        <textarea class="form-control" rows="3" wire:model="fields.config.completed_text"></textarea>
        <div class="form-text">{{ __('One item per line.') }}</div>
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
