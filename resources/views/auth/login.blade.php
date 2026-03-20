<x-guest-layout variant="simple">
    <x-auth.simple-brand mark-only class="mb-4" mark-class="p-1" />

    <div class="text-center mb-7">
        <h3 class="text-body-highlight mb-2">{{ __('Sign In') }}</h3>
        <p class="text-body-tertiary mb-0">Get access to your overlay composers and streamer tools</p>
    </div>

    <x-auth.social-button href="#" icon="fa-brands fa-twitch" iconColorClass="text-primary" class="mb-3" aria-disabled="true">
        {{ __('Sign in with Twitch') }}
    </x-auth.social-button>
    <x-auth.social-button href="#" icon="fa-brands fa-discord" iconColorClass="text-info" aria-disabled="true">
        {{ __('Sign in with Discord') }}
    </x-auth.social-button>

    <div class="position-relative">
        <hr class="bg-body-secondary mt-5 mb-4" />
        <div class="divider-content-center">{{ __('or use email') }}</div>
    </div>

    <x-validation-errors class="mb-4" />

    @session('status')
        <div class="alert alert-success mb-4">
            {{ $value }}
        </div>
    @endsession

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-auth.icon-field
            id="email"
            name="email"
            :label="__('Email address')"
            icon="fa-solid fa-envelope"
            type="email"
            :value="old('email')"
            placeholder="name@example.com"
            required
            autofocus
            autocomplete="username"
        />

        <x-auth.icon-field
            id="password"
            name="password"
            :label="__('Password')"
            icon="fa-solid fa-key"
            type="password"
            :placeholder="__('Password')"
            required
            autocomplete="current-password"
        />

        <div class="row flex-between-center mb-7">
            <div class="col-auto">
                <div class="form-check mb-0">
                    <x-checkbox id="remember_me" name="remember" :checked="old('remember')" />
                    <label class="form-check-label mb-0" for="remember_me">{{ __('Remember me') }}</label>
                </div>
            </div>

            @if (Route::has('password.request'))
                <div class="col-auto">
                    <a class="fs-9 fw-semibold" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                </div>
            @endif
        </div>

        <x-button class="w-100 mb-3">{{ __('Sign In') }}</x-button>

        @if (Route::has('register'))
            <div class="text-center">
                <a class="fs-9 fw-bold" href="{{ route('register') }}">{{ __('Create an account') }}</a>
            </div>
        @endif
    </form>
</x-guest-layout>
