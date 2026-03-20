<x-form-section submit="createTeam">
    <x-slot name="title">
        {{ __('Team Details') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Create a new team to collaborate with others on projects.') }}
    </x-slot>

    <x-slot name="form">
        <div class="col-12">
            <x-label value="{{ __('Team Owner') }}" />

            <div class="d-flex align-items-center mt-2">
                <img class="rounded-circle" src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" style="width: 3rem; height: 3rem; object-fit: cover;">

                <div class="ms-4 lh-sm">
                    <div class="text-body">{{ $this->user->name }}</div>
                    <div class="small text-body-secondary">{{ $this->user->email }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-8">
            <x-label for="name" value="{{ __('Team Name') }}" />
            <x-input id="name" type="text" class="mt-1" wire:model="state.name" autofocus />
            <x-input-error for="name" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-button>
            {{ __('Create') }}
        </x-button>
    </x-slot>
</x-form-section>
