<x-guest-layout variant="card">
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-4" />

        <div class="text-center text-lg-start mb-4">
            <h1 class="h3 mb-2">{{ __('Set a new password') }}</h1>
            <p class="text-body-secondary mb-0">Finish the recovery flow and return to the migrated Bootstrap/Phoenix account shell.</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="mb-3">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="mt-2" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            </div>

            <div class="mb-3">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="mt-2" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mb-4">
                <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-input id="password_confirmation" class="mt-2" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="d-grid">
                <x-button>{{ __('Reset Password') }}</x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
