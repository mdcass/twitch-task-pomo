<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="small text-uppercase fw-bold text-body-tertiary mb-2">{{ __('Team Ownership') }}</div>
            <h1 class="h2 mb-1">{{ __('Team Settings') }}</h1>
            <p class="text-body-secondary mb-0">Manage the current team record, membership, roles, and destructive actions from the Phoenix-based account shell.</p>
        </div>
    </x-slot>

    <div class="d-flex flex-column gap-4">
        @livewire('teams.update-team-name-form', ['team' => $team])
        @livewire('teams.team-member-manager', ['team' => $team])

        @if (Gate::check('delete', $team) && ! $team->personal_team)
            <x-section-border />
            @livewire('teams.delete-team-form', ['team' => $team])
        @endif
    </div>
</x-app-layout>
