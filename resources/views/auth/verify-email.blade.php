<x-guest-layout variant="card">
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="text-center text-lg-start mb-4">
            <h1 class="h3 mb-2">{{ __('Verify your email address') }}</h1>
            <p class="text-body-secondary mb-0">{{ __('One more step before you continue into the dashboard shell.') }}</p>
        </div>

        <div class="alert alert-info mb-4">
            {{ __('Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="alert alert-success mb-4">
                {{ __('A new verification link has been sent to the email address you provided in your profile settings.') }}
            </div>
        @endif

        <div class="d-flex flex-column gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <div class="d-grid">
                    <x-button type="submit">{{ __('Resend Verification Email') }}</x-button>
                </div>
            </form>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 small">
                <a
                    href="{{ route('profile.show') }}"
                    class="fw-semibold text-decoration-none"
                >
                    {{ __('Edit Profile') }}</a>

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf

                    <button type="submit" class="btn btn-link p-0 text-decoration-none fw-semibold">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </x-authentication-card>
</x-guest-layout>
