<x-overlay-widget-layout :title="$title">
    <article class="overlay-widget-card overlay-widget-card--artboard overlay-widget-card--pomodoro">
        @include('widgets.shared.pomodoro', get_defined_vars())
    </article>

    @include('widgets.shared.pomodoro-script', get_defined_vars())
</x-overlay-widget-layout>
