@props(['id', 'maxWidth'])

@php
    $id = $id ?? md5($attributes->wire('model'));

    $maxWidth = [
        'sm' => 'modal-sm',
        'md' => '',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        '2xl' => 'modal-xl',
    ][$maxWidth ?? '2xl'];
@endphp

<div x-data="{ show: @entangle($attributes->wire('model')) }" x-on:close.stop="show = false" x-on:keydown.escape.window="show = false"
    x-init="$watch('show', value => { document.body.classList.toggle('modal-open', value);
        document.body.style.overflow = value ? 'hidden' : ''; })">
    <div x-show="show" class="jetstream-modal-backdrop" x-on:click="show = false" x-transition.opacity
        style="display: none;"></div>

    <div x-show="show" id="{{ $id }}" class="jetstream-modal modal" x-bind:class="{ 'd-block show': show }"
        tabindex="-1" role="dialog" aria-modal="true" style="display: none;" x-transition.opacity
        x-on:click.self="show = false">
        <div class="modal-dialog modal-dialog-centered {{ $maxWidth }}">
            <div class="modal-content border border-translucent shadow-lg" x-trap.inert.noscroll="show">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
