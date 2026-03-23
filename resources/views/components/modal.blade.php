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

<div x-data="twitchTaskPomoModal({
    show: @entangle($attributes->wire('model')),
    dismissible: @js($dismissible),
    initialFocus: @js($initialFocus),
    initialFocusMethod: @js($initialFocusMethod),
})" x-init="init()" x-on:close.stop="show = false"
    x-on:keydown.escape.window="if (show) close()" x-cloak>
    <div x-show="show" class="jetstream-modal-backdrop fade" x-bind:class="{ 'show': show }" x-on:click="close()"
        x-transition.opacity style="display: none;"></div>

    <div x-show="show" id="{{ $id }}" class="jetstream-modal modal fade"
        x-bind:class="{ 'd-block show': show }" tabindex="-1" role="dialog" aria-modal="true"
        @if ($hasHeader) aria-labelledby="{{ $titleId }}" @endif
        @if ($hasBody) aria-describedby="{{ $bodyId }}" @endif
        x-bind:aria-hidden="show ? 'false' : 'true'" x-on:click.self="close()" style="display: none;">
        <div class="modal-dialog modal-dialog-centered {{ $maxWidth }}" x-transition.opacity.duration.200ms>
            <div class="modal-content border border-translucent shadow-lg" x-trap.inert.noscroll="show" x-ref="dialog">
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
</div>
