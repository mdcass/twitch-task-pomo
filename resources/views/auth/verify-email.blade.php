<x-guest-layout variant="simple">
    <x-auth.simple-brand mark-only class="mb-4" mark-class="p-1" />

    <div class="text-center mb-4">
        <h1 class="h3 mb-2">{{ __('Verify your email address') }}</h1>
        <p class="mb-0">{{ __('Please click on the link sent to your email address. If you didn\'t receive the email, we will gladly send you another.') }}</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-outline-success mb-4">
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

        <div class="d-flex align-items-center justify-content-end flex-wrap gap-3 small">
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf

                <button type="submit" class="btn btn-link p-0 text-decoration-none">
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
