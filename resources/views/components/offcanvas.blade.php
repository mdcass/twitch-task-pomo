@props([
    'id' => null,
    'width' => 'lg',
    'placement' => 'end',
    'dismissible' => true,
    'initialFocus' => null,
    'initialFocusMethod' => 'focus',
])

@php
    $wireModel = (string) $attributes->wire('model');

    $id = $id ?? md5($wireModel);

    $widthValue =
        [
            'sm' => '22rem',
            'md' => '28rem',
            'lg' => '34rem',
            'xl' => '40rem',
        ][$width] ?? '34rem';

    $hasHeader = isset($header) && trim((string) $header) !== '';
    $hasFooter = isset($footer) && trim((string) $footer) !== '';
    $hasBody = trim((string) $slot) !== '';

    $titleId = "{$id}-title";
    $bodyId = "{$id}-body";
@endphp

<div {{ $attributes->whereDoesntStartWith('wire:model')->class([
        'offcanvas border-start border-translucent shadow-lg',
        'offcanvas-start' => $placement === 'start',
        'offcanvas-end' => $placement !== 'start',
    ]) }}
    x-data="overlayOffcanvas({
        show: @entangle($attributes->wire('model')),
        dismissible: @js($dismissible),
        initialFocus: @js($initialFocus),
        initialFocusMethod: @js($initialFocusMethod),
    })" x-init="init()" x-on:close.stop="close()" x-cloak id="{{ $id }}"
    wire:ignore.self tabindex="-1" data-bs-backdrop="{{ $dismissible ? 'true' : 'static' }}"
    data-bs-keyboard="{{ $dismissible ? 'true' : 'false' }}" aria-hidden="true" @if ($hasHeader)
    aria-labelledby="{{ $titleId }}"
    @endif
    @if ($hasBody)
        aria-describedby="{{ $bodyId }}"
    @endif
    style="width: {{ $widthValue }};">
    @if ($hasHeader)
        <div class="offcanvas-header border-bottom">
            <div class="w-100" id="{{ $titleId }}">
                {{ $header }}
            </div>
        </div>
    @endif

    <div class="offcanvas-body" id="{{ $bodyId }}" x-ref="panel">
        {{ $slot }}
    </div>

    @if ($hasFooter)
        <div class="offcanvas-footer border-top px-4 py-3">
            {{ $footer }}
        </div>
    @endif
</div>
