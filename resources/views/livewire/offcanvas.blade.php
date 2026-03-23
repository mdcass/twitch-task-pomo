<x-offcanvas wire:model.live="open" :id="$elementId" :width="$width" :placement="$placement" :dismissible="$dismissible"
    :initial-focus="$initialFocus" :initial-focus-method="$initialFocusMethod"
    x-on:overlay-offcanvas-load.window="$wire.loaded($event.detail ?? {})"
    x-on:overlay-offcanvas-open.window="$wire.openFromEvent($event.detail ?? {})"
    x-on:overlay-offcanvas-close.window="$wire.closeFromEvent($event.detail ?? {})">
    @if ($title)
        <x-slot name="header">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <h5 class="offcanvas-title mb-0">{{ $title }}</h5>

                @if ($dismissible)
                    <button type="button" class="btn btn-sm btn-phoenix-secondary" x-on:click="$wire.set('open', false)">
                        <span class="fas fa-times" aria-hidden="true"></span>
                        <span class="visually-hidden">{{ __('Close') }}</span>
                    </button>
                @endif
            </div>
        </x-slot>
    @endif

    @if ($readyToLoad)
        @if ($componentName)
            @livewire($componentName, $this->resolved_component_props, key($this->child_key))
        @elseif ($templateName)
            @include($templateName, $this->resolved_template_props)
        @endif
    @else
        <div class="d-flex align-items-center gap-3 text-body-secondary">
            <div class="spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">{{ __('Loading...') }}</span>
            </div>

            <span>{{ __('Loading...') }}</span>
        </div>
    @endif
</x-offcanvas>
