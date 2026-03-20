<x-guest-layout variant="card">
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="text-center text-lg-start mb-4">
            <h1 class="h3 mb-2">{{ __('Confirm your password') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('This protected action requires a quick password confirmation.') }}</p>
        </div>

        <div class="alert alert-info mb-4">
            {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <div class="mb-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="mt-2" type="password" name="password" required autocomplete="current-password" autofocus />
            </div>

            <div class="d-grid">
                <x-button>{{ __('Confirm') }}</x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
