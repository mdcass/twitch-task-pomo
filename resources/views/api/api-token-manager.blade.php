<div>
    <!-- Generate API Token -->
    <x-form-section submit="createApiToken">
        <x-slot name="title">
            {{ __('Create API Token') }}
        </x-slot>

        <x-slot name="description">
            {{ __('API tokens allow third-party services to authenticate with our application on your behalf.') }}
        </x-slot>

        <x-slot name="form">
            <!-- Token Name -->
            <div class="col-12 col-sm-8">
                <x-label for="name" value="{{ __('Token Name') }}" />
                <x-input id="name" type="text" class="mt-1" wire:model="createApiTokenForm.name" autofocus />
                <x-input-error for="name" class="mt-2" />
            </div>

            <!-- Token Permissions -->
            @if (Laravel\Jetstream\Jetstream::hasPermissions())
                <div class="col-12">
                    <x-label for="permissions" value="{{ __('Permissions') }}" />

                    <div class="row row-cols-1 row-cols-md-2 g-3 mt-1">
                        @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                            <label class="col d-flex align-items-center gap-2">
                                <x-checkbox wire:model="createApiTokenForm.permissions" :value="$permission" />
                                <span class="small text-body-secondary">{{ $permission }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-slot>

        <x-slot name="actions">
            <x-action-message class="me-3" on="created">
                {{ __('Created.') }}
            </x-action-message>

            <x-button>
                {{ __('Create') }}
            </x-button>
        </x-slot>
    </x-form-section>

    @if ($this->user->tokens->isNotEmpty())
        <x-section-border />

        <!-- Manage API Tokens -->
        <div class="mt-5">
            <x-action-section>
                <x-slot name="title">
                    {{ __('Manage API Tokens') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('You may delete any of your existing tokens if they are no longer needed.') }}
                </x-slot>

                <!-- API Token List -->
                <x-slot name="content">
                    <div class="d-flex flex-column gap-4">
                        @foreach ($this->user->tokens->sortBy('name') as $token)
                            <div
                                class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                <div style="word-break: break-all;">
                                    {{ $token->name }}
                                </div>

                                <div class="d-flex align-items-center gap-3 ms-md-2">
                                    @if ($token->last_used_at)
                                        <div class="small text-body-tertiary">
                                            {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                        </div>
                                    @endif

                                    @if (Laravel\Jetstream\Jetstream::hasPermissions())
                                        <button
                                            class="btn btn-link btn-sm p-0 text-body-tertiary text-decoration-underline"
                                            wire:click="manageApiTokenPermissions({{ $token->id }})">
                                            {{ __('Permissions') }}
                                        </button>
                                    @endif

                                    <button class="btn btn-link btn-sm p-0 text-danger"
                                        wire:click="confirmApiTokenDeletion({{ $token->id }})">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-slot>
            </x-action-section>
        </div>
    @endif

    <!-- Token Value Modal -->
    <x-modal wire:model.live="displayingToken" initial-focus="plaintextToken" initial-focus-method="select">
        <x-slot name="header">
            <h5 class="modal-title">{{ __('API Token') }}</h5>
        </x-slot>

        <div>
            <div>
                {{ __('Please copy your new API token. For your security, it won\'t be shown again.') }}
            </div>

            <x-input x-ref="plaintextToken" type="text" readonly :value="$plainTextToken"
                class="mt-4 font-monospace text-body-secondary bg-body-secondary" autofocus autocomplete="off"
                autocorrect="off" autocapitalize="off" spellcheck="false" />

            <x-slot name="footer">
                <x-secondary-button wire:click="$set('displayingToken', false)" wire:loading.attr="disabled">
                    {{ __('Close') }}
                </x-secondary-button>
            </x-slot>
    </x-modal>

    <!-- API Token Permissions Modal -->
    <x-modal wire:model.live="managingApiTokenPermissions">
        <x-slot name="header">
            <h5 class="modal-title">{{ __('API Token Permissions') }}</h5>
        </x-slot>

        <div class="row row-cols-1 row-cols-md-2 g-3">
            @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                <label class="col d-flex align-items-center gap-2">
                    <x-checkbox wire:model="updateApiTokenForm.permissions" :value="$permission" />
                    <span class="small text-body-secondary">{{ $permission }}</span>
                </label>
            @endforeach
        </div>

        <x-slot name="footer">
            <x-secondary-button wire:click="$set('managingApiTokenPermissions', false)" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-button class="ms-3" wire:click="updateApiToken" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-button>
        </x-slot>
    </x-modal>

    <!-- Delete Token Confirmation Modal -->
    <x-modal wire:model.live="confirmingApiTokenDeletion" max-width="md">
        <x-slot name="header">
            <h5 class="modal-title">{{ __('Delete API Token') }}</h5>
        </x-slot>

        <div class="d-flex align-items-start gap-3">
            <div class="d-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger shrink-0"
                style="width: 2.5rem; height: 2.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>

            <div class="text-body-secondary">
                {{ __('Are you sure you would like to delete this API token?') }}
            </div>
        </div>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmingApiTokenDeletion')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="deleteApiToken" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </x-danger-button>
        </x-slot>
    </x-modal>
</div>
