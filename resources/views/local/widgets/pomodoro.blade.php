<x-local-tooling-layout :title="$title">
    <main class="container-fluid min-vh-100 py-5">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-lg-7 col-xxl-5">
                <div class="card border-0 shadow-lg overflow-hidden d-flex justify-content-center p-4">
                    <div class="overlay-widget-card overlay-widget-card--artboard overlay-widget-card--pomodoro">
                        @include('widgets.shared.pomodoro', get_defined_vars())
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('widgets.shared.pomodoro-script', get_defined_vars())
</x-local-tooling-layout>
