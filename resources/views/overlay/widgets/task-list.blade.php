<x-overlay-widget-layout :title="$title">
    <article class="overlay-widget-card overlay-widget-card--artboard overlay-widget-card--task-list">
        @include('widgets.shared.task-list', get_defined_vars())
    </article>
</x-overlay-widget-layout>
