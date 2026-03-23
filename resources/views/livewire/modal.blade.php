<div x-data x-on:twitch-modal-load.window="$wire.loaded($event.detail ?? {})"
    x-on:twitch-modal-open.window="$wire.openFromEvent($event.detail ?? {})"
    x-on:twitch-modal-close.window="$wire.closeFromEvent($event.detail ?? {})">
    <x-modal wire:model.live="open" :id="$elementId" :max-width="$maxWidth" :dismissible="$dismissible" :initial-focus="$initialFocus"
        :initial-focus-method="$initialFocusMethod">
        @if ($title)
            <x-slot name="header">
                <h5 class="modal-title">{{ $title }}</h5>
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
    </x-modal>
</div>
