<x-app-layout>
    <x-slot name="contentTop">
        <x-content-top-nav>
            <x-slot name="primary">
                <x-breadcrumbs :items="[
                    ['label' => __('Dashboard'), 'href' => route('dashboard', absolute: false)],
                    ['label' => __('Widgets'), 'current' => true],
                ]" />
            </x-slot>
        </x-content-top-nav>
    </x-slot>

    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h1 class="h2 mb-1">{{ __('Widgets') }}</h1>
                <p class="text-body-secondary mb-0">
                    {{ __('Manage reusable proprietary widgets for canvas placement and optional standalone browser-source URLs.') }}
                </p>
            </div>
        </div>
    </x-slot>

    @livewire('widgets.widget-index')
</x-app-layout>
