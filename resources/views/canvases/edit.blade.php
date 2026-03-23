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

            @can('delete', $canvas)
                <div class="dropdown">
                    <button type="button" class="btn btn-phoenix-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="fas fa-ellipsis-h me-2" aria-hidden="true"></span>
                        {{ __('Actions') }}
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end py-2">
                        <li>
                            <x-overlay-trigger
                                class="dropdown-item text-danger"
                                id="canvas-lifecycle-modal"
                                surface="modal"
                                :data="[
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
    </x-slot>

    <div class="row gx-lg-9">
        <div class="col-xl-9 border-end-xl">
            <div class="card border border-translucent shadow-sm">
                <div class="card-header">
                    <h2 class="h5 mb-1">{{ __('Workspace') }}</h2>
                    <p class="text-body-secondary mb-0">
                        {{ __('Composer controls arrive in the follow-on task. This placeholder reserves the canvas surface and aspect ratio now.') }}
                    </p>
                </div>

                <div class="card-body">
                    <div class="rounded-4 border border-dashed border-primary-subtle bg-body-highlight p-3 p-lg-4">
                        <div class="rounded-4 border border-translucent bg-body shadow-sm overflow-hidden mx-auto"
                             style="aspect-ratio: {{ $canvas->width }} / {{ $canvas->height }};">
                            <div
                                class="h-100 d-flex flex-column align-items-center justify-content-center text-center p-4 p-lg-5">
                                <div
                                    class="icon-wrapper icon-wrapper-lg bg-primary-subtle text-primary rounded-circle mb-3">
                                    <span class="uil uil-panorama-h" aria-hidden="true"></span>
                                </div>
                                <h3 class="h4 mb-2">{{ __('Canvas workspace reserved') }}</h3>
                                <p class="text-body-secondary mb-3">
                                    {{ __('This slice establishes canvas metadata and page structure. Widget add, move, resize, and configure behavior is intentionally deferred.') }}
                                </p>
                                <div class="badge badge-phoenix badge-phoenix-primary">{{ $canvas->width }} x
                                    {{ $canvas->height }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">{{ __('Metadata') }}</h3>
                @can('update', $canvas)
                    <x-overlay-trigger
                        class="btn btn-sm btn-phoenix-secondary"
                        id="canvas-edit-offcanvas"
                        surface="offcanvas">
                        {{ __('Edit') }}
                    </x-overlay-trigger>
                @endcan
            </div>
            <div class="mb-2">
                <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Name') }}
                </div>
                <div class="fw-semibold text-body-emphasis">{{ $canvas->name }}</div>
            </div>

            <div class="mb-2">
                <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">
                    {{ __('Dimensions') }}</div>
                <div class="fw-semibold text-body-emphasis">{{ $canvas->width }} x {{ $canvas->height }}
                </div>
            </div>

            <div class="mb-2">
                <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ __('Created') }}
                </div>
                <div class="text-body-secondary">{{ $canvas->created_at->format('j M Y, H:i') }}</div>
            </div>

            <div class="mb-2">
                <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">
                    {{ __('Last updated') }}</div>
                <div class="text-body-secondary">{{ $canvas->updated_at->format('j M Y, H:i') }}</div>
            </div>
        </div>
    </div>

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

        @livewire('modal', [
            'component' => 'canvases.canvas-lifecycle-modal',
            'elementId' => 'canvas-lifecycle-modal',
            'title' => 'Archive Canvas',
            'maxWidth' => 'md',
        ])
    @endpush
</x-app-layout>
