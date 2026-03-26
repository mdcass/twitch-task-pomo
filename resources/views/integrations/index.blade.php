<x-app-layout>
    <x-slot name="contentTop">
        <x-content-top-nav>
            <x-slot name="primary">
                <x-breadcrumbs :items="[
                    ['label' => __('Dashboard'), 'href' => route('dashboard', absolute: false)],
                    ['label' => __('Integrations'), 'current' => true],
                ]" />
            </x-slot>
        </x-content-top-nav>
    </x-slot>

    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h1 class="h2 mb-1">{{ __('Integrations') }}</h1>
                <p class="text-body-secondary mb-0">
                    {{ __('Provider connections are user-owned in v1 and resolved through the current team owner for provider-backed widgets.') }}
                </p>
            </div>
        </div>
    </x-slot>

    @livewire('integrations.integration-index')
</x-app-layout>
