<div>
    @if ($widget && $definition)
        <form wire:submit="submit" class="d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="canvas-widget-name">{{ __('Name') }}</label>
                <input id="canvas-widget-name" type="text" class="form-control" wire:model="fields.name">
            </div>

            @include($definition->editorView(), ['fields' => $fields])

            @if ($definition->requiredProvider() !== null)
                <div class="alert alert-subtle-warning border border-warning-subtle mb-0 small">
                    {{ __('Provider connect, repair, publish, and archive actions stay on the full widget page.') }}
                </div>
            @endif

            <div class="d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary">{{ __('Save Shared Widget') }}</button>
            </div>
        </form>
    @else
        <div class="text-body-secondary small">{{ __('Select a widget to edit.') }}</div>
    @endif
</div>
