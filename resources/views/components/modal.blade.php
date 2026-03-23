@props([
    'id' => null,
    'maxWidth' => '2xl',
    'dismissible' => true,
    'initialFocus' => null,
    'initialFocusMethod' => 'focus',
])

@php
    $wireModel = (string) $attributes->wire('model');

    $id = $id ?? md5($wireModel);

    $maxWidth =
        [
            'sm' => 'modal-sm',
            'md' => '',
            'lg' => 'modal-lg',
            'xl' => 'modal-xl',
            '2xl' => 'modal-xl',
        ][$maxWidth] ?? 'modal-xl';

    $hasHeader = isset($header) && trim((string) $header) !== '';
    $hasFooter = isset($footer) && trim((string) $footer) !== '';
    $hasBody = trim((string) $slot) !== '';

    $titleId = "{$id}-title";
    $bodyId = "{$id}-body";
@endphp

<div {{ $attributes->whereDoesntStartWith('wire:model')->merge(['id' => $id, 'class' => 'jetstream-modal modal fade']) }}
    x-data="overlayModal({
        show: @entangle($attributes->wire('model')),
        dismissible: @js($dismissible),
        initialFocus: @js($initialFocus),
        initialFocusMethod: @js($initialFocusMethod),
    })"
    x-init="init()" x-on:close.stop="close()" x-cloak wire:ignore.self tabindex="-1"
    data-bs-backdrop="{{ $dismissible ? 'true' : 'static' }}"
    data-bs-keyboard="{{ $dismissible ? 'true' : 'false' }}"
    @if ($hasHeader) aria-labelledby="{{ $titleId }}" @endif
    @if ($hasBody) aria-describedby="{{ $bodyId }}" @endif
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered {{ $maxWidth }}">
        <div class="modal-content border border-translucent shadow-lg" x-ref="dialog">
            @if ($hasHeader)
                <div class="modal-header">
                    <div class="w-100" id="{{ $titleId }}">
                        {{ $header }}
                    </div>
                </div>
            @endif

            <div class="modal-body" id="{{ $bodyId }}">
                {{ $slot }}
            </div>

            @if ($hasFooter)
                <div class="modal-footer">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
