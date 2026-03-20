<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">{{ __('Team Setup') }}</div>
            <h1 class="h2 mb-1">{{ __('Create Team') }}</h1>
            <p class="text-body-secondary mb-0">Establish the team ownership boundary that the product uses for canvases, widgets, and future Twitch integrations.</p>
        </div>
    </x-slot>

    @livewire('teams.create-team-form')
</x-app-layout>
