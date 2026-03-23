@props([
    'title' => __('Confirm Password'),
    'content' => __('For your security, please confirm your password to continue.'),
    'button' => __('Confirm'),
])

@php
    $confirmableId = md5($attributes->wire('then'));
@endphp

<span {{ $attributes->wire('then') }} x-data x-ref="span"
    x-on:click="$wire.startConfirmingPassword('{{ $confirmableId }}')"
    x-on:password-confirmed.window="setTimeout(() => $event.detail.id === '{{ $confirmableId }}' && $refs.span.dispatchEvent(new CustomEvent('then', { bubbles: false })), 250);">
    {{ $slot }}
</span>

@once
    <x-modal wire:model.live="confirmingPassword" initial-focus="confirmable_password">
        <x-slot name="header">
            <h5 class="modal-title">{{ $title }}</h5>
        </x-slot>

        <div>
            <div class="text-body-secondary">
                {{ $content }}
            </div>

            <div class="mt-4">
                <x-input type="password" class="mt-1 w-75" placeholder="{{ __('Password') }}" autocomplete="current-password"
                    x-ref="confirmable_password" wire:model="confirmablePassword" wire:keydown.enter="confirmPassword" />

                <x-input-error for="confirmable_password" class="mt-2" />
            </div>
        </div>

        <x-slot name="footer">
            <x-secondary-button wire:click="stopConfirmingPassword" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-button class="ms-3" dusk="confirm-password-button" wire:click="confirmPassword"
                wire:loading.attr="disabled">
                {{ $button }}
            </x-button>
        </x-slot>
    </x-modal>
@endonce
