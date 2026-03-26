<x-app-layout>
    <x-slot name="contentTop">
        <x-content-top-nav>
            <x-slot name="primary">
                <x-breadcrumbs :items="[
                    ['label' => __('Dashboard'), 'href' => route('dashboard', absolute: false)],
                    ['label' => __('Widgets'), 'href' => route('widgets.index', absolute: false)],
                    ['label' => $widget->displayName(), 'current' => true],
                ]" />
            </x-slot>
        </x-content-top-nav>
    </x-slot>

    <x-slot name="header">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ $definition->label() }}</div>
                <h1 class="h2 mb-1">{{ $widget->displayName() }}</h1>
                <p class="text-body-secondary mb-0">
                    {{ __('Reusable proprietary widget. Remote embeds stay canvas-scoped and are managed from canvas pages only.') }}
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2 small">
                <span class="badge badge-phoenix badge-phoenix-secondary">{{ $widget->lifecycle_state->label() }}</span>
                <span class="badge badge-phoenix badge-phoenix-info">{{ $widget->canvas_widgets_count }} {{ __('canvas placements') }}</span>
                <span class="badge badge-phoenix {{ $widget->isPublished() ? 'badge-phoenix-success' : 'badge-phoenix-warning' }}">
                    {{ $widget->isPublished() ? __('Published') : __('Unpublished') }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="d-flex flex-column gap-4">
        @if ($requiredProvider && ! $providerAuth)
            <div class="alert alert-subtle-warning border border-warning-subtle mb-0">
                <div class="fw-semibold mb-1">
                    {{ __('Owner action required: connect :provider', ['provider' => $requiredProvider->label()]) }}
                </div>
                <div class="small text-body-secondary">
                    {{ __('Provider-backed widgets remain attachable as drafts, but only the current team owner can connect or repair the integration that makes them render.') }}
                </div>
            </div>
        @endif

        @livewire('widgets.widget-editor', ['widgetId' => $widget->id], key('widget-editor-'.$widget->id))
    </div>
</x-app-layout>
