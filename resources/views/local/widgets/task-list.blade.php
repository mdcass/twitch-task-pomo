<x-local-tooling-layout :title="$title">
    <main class="container-fluid min-vh-100 py-5">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-lg-8 col-xxl-6">
                <div class="card border-0 shadow-lg overflow-hidden d-flex justify-content-center p-4">
                    <div class="overlay-widget-card overlay-widget-card--artboard overlay-widget-card--task-list">
                        @include('widgets.shared.task-list', get_defined_vars())
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-local-tooling-layout>
