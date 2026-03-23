<x-local-tooling-layout :title="$title">
    <main class="container-fluid min-vh-100 py-5">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-lg-8 col-xxl-6">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex flex-column flex-sm-row justify-content-between gap-3 mb-4">
                            <div>
                                <div class="small text-uppercase fw-bold text-body-tertiary mb-2">Local Widget
                                    Preview</div>
                                <h1 class="h2 mb-2">{{ $title }}</h1>
                                <p class="text-body-secondary mb-0">Stateless task list preview for future canvas
                                    embedding.</p>
                            </div>

                            <div class="d-flex align-items-start">
                                <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis px-3 py-2">
                                    {{ count($pendingItems) }} pending / {{ count($completedItems) }} completed
                                </span>
                            </div>
                        </div>

                        @if ($pendingItems === [] && $completedItems === [])
                            <div class="rounded-4 border border-dashed text-center py-5 px-4">
                                <h2 class="h5 mb-2">No tasks configured</h2>
                                <p class="text-body-secondary mb-0">Add <code>pending[]</code> or
                                    <code>completed[]</code> query params to seed the preview.
                                </p>
                            </div>
                        @else
                            <div class="row g-4">
                                <div class="col-12 col-xl-7">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h2 class="h5 mb-0">Pending</h2>
                                        <span class="small text-body-tertiary">{{ count($pendingItems) }} items</span>
                                    </div>

                                    <div class="list-group list-group-flush">
                                        @forelse ($pendingItems as $item)
                                            <div class="list-group-item px-0 py-3 border-bottom">
                                                <div class="d-flex align-items-start gap-3">
                                                    <span
                                                        class="badge rounded-pill bg-primary-subtle text-primary-emphasis mt-1">Next</span>
                                                    <div class="fw-semibold">{{ $item }}</div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="list-group-item px-0 py-3 border-bottom-0 text-body-secondary">
                                                No pending items.</div>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="col-12 col-xl-5">
                                    <div class="rounded-4 bg-body-tertiary p-4 h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h2 class="h5 mb-0">Completed</h2>
                                            <span class="small text-body-tertiary">{{ count($completedItems) }}
                                                items</span>
                                        </div>

                                        <div class="d-flex flex-column gap-3">
                                            @forelse ($completedItems as $item)
                                                <div class="rounded-3 border bg-body px-3 py-3">
                                                    <div class="small text-uppercase fw-bold text-success mb-2">Done
                                                    </div>
                                                    <div class="text-body-secondary text-decoration-line-through">
                                                        {{ $item }}</div>
                                                </div>
                                            @empty
                                                <p class="text-body-secondary mb-0">No completed items.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-local-tooling-layout>
