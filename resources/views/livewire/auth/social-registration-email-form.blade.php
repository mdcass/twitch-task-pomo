<div>
    <x-auth.simple-brand mark-only class="mb-4" mark-class="p-1" />

    <div class="text-center mb-4">
        <h1 class="h3 mb-2">{{ $this->isCollectingEmail ? __('Finish Sign Up') : __('Account Match Found') }}</h1>
        <p class="mb-0">{{ $this->displayName }}</p>
    </div>

    @if ($this->debugOverride !== null)
        <div class="alert alert-outline-warning mb-4">
            <div class="fw-semibold">Local debug override active</div>
            <div class="small mb-0">{{ $this->debugOverride }}</div>
        </div>
    @endif

    @error('workflow')
        <div class="alert alert-outline-danger mb-4">{{ $message }}</div>
    @enderror

    @if ($this->isCollectingEmail)
        <div class="text-center mb-4">
            <p class="mb-0">Please provide an email address for your account</p>
        </div>
        <form wire:submit="submit" class="mt-4">
            <div class="mb-3 text-start">
                <x-auth.field-label for="social_email" :value="__('Email address')" />
                <x-input
                    id="social_email"
                    type="email"
                    wire:model.blur="fields.email"
                    autocomplete="username"
                    required
                />
                <x-input-error for="fields.email" class="mt-2" />
            </div>

            <x-button class="w-100 mb-3" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">{{ __('Continue') }}</span>
                <span wire:loading wire:target="submit">{{ __('Saving...') }}</span>
            </x-button>

            <div class="text-center">
                <a class="fs-9 fw-bold" href="{{ route('register') }}">
                    {{ __('Back to registration') }}
                </a>
            </div>
        </form>
    @elseif ($this->isExistingAccountHandoff)
        <div class="text-center mb-4">
            <div class="fw-semibold mb-2">The email address for your {{ $this->providerLabel }} account already belongs to an existing account here.</div>
            <p class="mb-0">
                Sign in with your original registration method for
                <span class="fw-bolder">{{ $this->attemptedEmail }}</span>
                first, then link {{ $this->providerLabel }} from account settings in a later task.
            </p>
        </div>

        <div class="d-grid gap-2">
            <a class="btn btn-primary fw-bolder" href="{{ route('login') }}">{{ __('Sign In') }}</a>
            <a class="btn btn-outline-secondary fw-semibold" href="{{ route('register') }}">{{ __('Back to Register') }}</a>
        </div>
    @endif
</div>
