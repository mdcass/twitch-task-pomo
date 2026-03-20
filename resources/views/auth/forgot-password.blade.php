<x-guest-layout variant="card">
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="text-center text-lg-start mb-4">
            <h1 class="h3 mb-2">{{ __('Forgot your password?') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('Enter your email below and we will send you a reset link.') }}</p>
        </div>

        <div class="alert alert-info mb-4">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        @session('status')
            <div class="alert alert-success mb-4">
                {{ $value }}
            </div>
        @endsession

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-4">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="mt-2" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="d-grid">
                <x-button>{{ __('Email Password Reset Link') }}</x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
