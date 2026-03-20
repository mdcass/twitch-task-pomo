<x-guest-layout variant="card">
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div x-data="{ recovery: false }">
            <div class="text-center text-lg-start mb-4">
                <h1 class="h3 mb-2">{{ __('Two-factor challenge') }}</h1>
                <p class="text-body-secondary mb-0" x-show="! recovery">
                    {{ __('Enter the code from your authenticator application to continue.') }}
                </p>
                <p class="text-body-secondary mb-0" x-cloak x-show="recovery">
                    {{ __('Use one of your recovery codes if you cannot access your authenticator.') }}
                </p>
            </div>

            <x-validation-errors class="mb-4" />

            <form method="POST" action="{{ route('two-factor.login') }}">
                @csrf

                <div class="mb-3" x-show="! recovery">
                    <x-label for="code" value="{{ __('Code') }}" />
                    <x-input id="code" class="mt-2" type="text" inputmode="numeric" name="code" autofocus x-ref="code" autocomplete="one-time-code" />
                </div>

                <div class="mb-3" x-cloak x-show="recovery">
                    <x-label for="recovery_code" value="{{ __('Recovery Code') }}" />
                    <x-input id="recovery_code" class="mt-2" type="text" name="recovery_code" x-ref="recovery_code" autocomplete="one-time-code" />
                </div>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4">
                    <button type="button" class="btn btn-link px-0 text-decoration-none fw-semibold"
                                    x-show="! recovery"
                                    x-on:click="
                                        recovery = true;
                                        $nextTick(() => { $refs.recovery_code.focus() })
                                    ">
                        {{ __('Use a recovery code') }}
                    </button>

                    <button type="button" class="btn btn-link px-0 text-decoration-none fw-semibold"
                                    x-cloak
                                    x-show="recovery"
                                    x-on:click="
                                        recovery = false;
                                        $nextTick(() => { $refs.code.focus() })
                                    ">
                        {{ __('Use an authentication code') }}
                    </button>

                    <x-button>{{ __('Log in') }}</x-button>
                </div>
            </form>
        </div>
    </x-authentication-card>
</x-guest-layout>
