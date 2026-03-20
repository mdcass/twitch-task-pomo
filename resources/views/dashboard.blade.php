<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div class="small text-uppercase fw-bold text-body-tertiary mb-2">{{ __('Foundation MVP') }}</div>
                <h1 class="h2 mb-1">{{ __('Dashboard') }}</h1>
                <p class="text-body-secondary mb-0">Bootstrap and Phoenix now own the shared application shell for the upcoming composer and stream-management work.</p>
            </div>
        </div>
    </x-slot>

    <x-welcome />
</x-app-layout>
