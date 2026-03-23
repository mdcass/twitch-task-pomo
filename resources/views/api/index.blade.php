<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">{{ __('Developer Access') }}</div>
            <h1 class="h2 mb-1">{{ __('API Tokens') }}</h1>
            <p class="text-body-secondary mb-0">Manage personal access tokens inside the shared Bootstrap/Phoenix account
                surface.</p>
        </div>
    </x-slot>

    @livewire('api.api-token-manager')
</x-app-layout>
