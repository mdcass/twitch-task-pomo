<x-app-layout>
    <x-slot name="contentTop">
        <x-content-top-nav>
            <x-slot name="primary">
                <x-breadcrumbs :items="[
                    ['label' => __('Dashboard'), 'href' => route('dashboard', absolute: false)],
                    ['label' => __('Canvases'), 'href' => route('canvases.index', absolute: false)],
                    ['label' => $canvas->name, 'current' => true],
                ]" />
            </x-slot>
        </x-content-top-nav>
    </x-slot>

    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
            <div>
                <div class="small text-uppercase fw-bold text-body-tertiary mb-2">{{ __('Canvas editor') }}</div>
                <h1 class="h2 mb-1">{{ $canvas->name }}</h1>
                <p class="text-body-secondary mb-0">{{ $canvas->width }} x {{ $canvas->height }}</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @can('update', $canvas)
                    <x-overlay-trigger class="btn btn-primary" id="canvas-edit-offcanvas" surface="offcanvas">
                        <span class="fas fa-pen me-2" aria-hidden="true"></span>
                        {{ __('Edit canvas') }}
                    </x-overlay-trigger>

                    <div class="dropdown">
                        <button type="button" class="btn btn-phoenix-secondary dropdown-toggle" data-widget-add
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="fas fa-plus me-2" aria-hidden="true"></span>
                            {{ __('Add widget') }}
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end py-2">
                            <li>
                                <x-overlay-trigger class="dropdown-item" id="canvas-quick-create-widget-offcanvas"
                                    surface="offcanvas">
                                    {{ __('Quick-create widget') }}
                                </x-overlay-trigger>
                            </li>
                            <li>
                                <x-overlay-trigger class="dropdown-item" id="canvas-attach-widget-offcanvas"
                                    surface="offcanvas">
                                    {{ __('Attach widget') }}
                                </x-overlay-trigger>
                            </li>
                            <li>
                                <x-overlay-trigger class="dropdown-item" id="canvas-remote-widget-offcanvas"
                                    surface="offcanvas">
                                    {{ __('Add remote widget') }}
                                </x-overlay-trigger>
                            </li>
                        </ul>
                    </div>
                @endcan

                @can('delete', $canvas)
                    <div class="dropdown">
                        <button type="button" class="btn btn-phoenix-secondary" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <span class="fas fa-ellipsis-h me-2" aria-hidden="true"></span>
                            {{ __('Actions') }}
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end py-2">
                            <li>
                                <x-overlay-trigger class="dropdown-item text-danger" id="canvas-lifecycle-modal"
                                    surface="modal" :data="[
                                        'canvasId' => $canvas->id,
                                        'action' => 'archive',
                                        'redirectTo' => route('canvases.index', absolute: false),
                                    ]">
                                    {{ __('Archive') }}
                                </x-overlay-trigger>
                            </li>
                        </ul>
                    </div>
                @endcan
            </div>
        </div>
    </x-slot>

    @livewire('canvases.canvas-composer', ['canvasId' => $canvas->id])

    @push('modals')
        @livewire('offcanvas', [
            'component' => [
                'canvases.canvas-form',
                [
                    'mode' => 'edit',
                    'canvasId' => $canvas->id,
                    'redirectTo' => route('canvases.edit', $canvas, false),
                ],
            ],
            'elementId' => 'canvas-edit-offcanvas',
            'title' => 'Edit Canvas',
            'width' => 'lg',
            'initialFocus' => 'name',
        ])

        @livewire('offcanvas', [
            'component' => ['canvases.quick-create-widget-form', ['canvasId' => $canvas->id]],
            'elementId' => 'canvas-quick-create-widget-offcanvas',
            'title' => 'Quick-Create Widget',
            'width' => 'lg',
        ])

        @livewire('offcanvas', [
            'component' => ['canvases.attach-existing-widget-form', ['canvasId' => $canvas->id]],
            'elementId' => 'canvas-attach-widget-offcanvas',
            'title' => 'Attach Widget',
            'width' => 'lg',
        ])

        @livewire('offcanvas', [
            'component' => ['canvases.add-remote-widget-form', ['canvasId' => $canvas->id]],
            'elementId' => 'canvas-remote-widget-offcanvas',
            'title' => 'Add Remote Widget',
            'width' => 'lg',
            'initialFocus' => 'url',
        ])

        @livewire('offcanvas', [
            'component' => ['canvases.edit-widget-settings-form'],
            'elementId' => 'canvas-widget-settings-offcanvas',
            'title' => 'Edit Widget Settings',
            'width' => 'lg',
        ])

        @livewire('modal', [
            'component' => 'canvases.canvas-lifecycle-modal',
            'elementId' => 'canvas-lifecycle-modal',
            'title' => 'Archive Canvas',
            'maxWidth' => 'md',
        ])

        @livewire('modal', [
            'component' => 'canvases.widget-delete-modal',
            'elementId' => 'canvas-widget-delete-modal',
            'title' => 'Delete Widget',
            'maxWidth' => 'md',
        ])
    @endpush
</x-app-layout>
