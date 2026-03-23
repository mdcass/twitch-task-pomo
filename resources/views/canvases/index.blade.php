<x-app-layout>
    <x-slot name="contentTop">
        <x-content-top-nav>
            <x-slot name="primary">
                <x-breadcrumbs :items="[
                    ['label' => __('Dashboard'), 'href' => route('dashboard', absolute: false)],
                    ['label' => __('Canvases'), 'current' => true],
                ]" />
            </x-slot>
        </x-content-top-nav>
    </x-slot>

    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h1 class="h2 mb-1">{{ __('Canvases') }}</h1>
                <p class="text-body-secondary mb-0">
                    {{ __('Create a canvas of many widgets for one easy browser source in your streaming software') }}
                </p>
            </div>

            @can('create', \App\Models\Canvas::class)
                <x-overlay-trigger class="btn btn-primary" data-canvas-add id="canvas-create-offcanvas" surface="offcanvas">
                    <span class="fas fa-plus me-2" aria-hidden="true"></span>
                    {{ __('Add canvas') }}
                </x-overlay-trigger>
            @endcan
        </div>
    </x-slot>

    @livewire('canvases.canvas-index')

    @push('modals')
        @livewire('offcanvas', [
            'component' => ['canvases.canvas-form', ['mode' => 'create']],
            'elementId' => 'canvas-create-offcanvas',
            'title' => 'Add Canvas',
            'width' => 'lg',
            'initialFocus' => 'name',
        ])

        @livewire('offcanvas', [
            'component' => ['canvases.canvas-form', ['mode' => 'edit']],
            'elementId' => 'canvas-edit-offcanvas',
            'title' => 'Edit Canvas',
            'width' => 'lg',
            'initialFocus' => 'name',
        ])

        @livewire('modal', [
            'component' => 'canvases.canvas-lifecycle-modal',
            'elementId' => 'canvas-lifecycle-modal',
            'title' => 'Archive Canvas',
            'maxWidth' => 'md',
        ])
    @endpush
</x-app-layout>
