<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">{{ __('Account') }}</div>
            <h1 class="h2 mb-1">{{ __('Profile') }}</h1>
            <p class="text-body-secondary mb-0">Manage your account details, password, two-factor authentication, and
                active sessions.</p>
        </div>
    </x-slot>

    <div class="d-flex flex-column gap-4">
        @if (Laravel\Fortify\Features::canUpdateProfileInformation())
            @livewire('profile.update-profile-information-form')
            <x-section-border />
        @endif

        @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
            @livewire('profile.update-password-form')
            <x-section-border />
        @endif

        @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
            @livewire('profile.two-factor-authentication-form')
            <x-section-border />
        @endif

        @livewire('profile.logout-other-browser-sessions-form')

        @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
            <x-section-border />
            @livewire('profile.delete-user-form')
        @endif
    </div>
</x-app-layout>
