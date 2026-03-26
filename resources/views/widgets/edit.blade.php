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
                <div class="small text-uppercase fw-semibold text-body-tertiary mb-1">{{ $widget->type->label() }}</div>
                <h1 class="h2 mb-1">{{ $widget->displayName() }}</h1>
                <p class="text-body-secondary mb-0">
                    {{ __('Reusable proprietary widget for canvas placement and optional standalone browser-source output. Remote embeds stay canvas-scoped and are managed from canvas pages only.') }}
                </p>
            </div>
        </div>
    </x-slot>

    @livewire('widgets.widget-editor', ['widgetId' => $widget->id], key('widget-editor-' . $widget->id))
</x-app-layout>
