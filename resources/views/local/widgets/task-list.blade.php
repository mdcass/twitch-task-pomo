<x-local-tooling-layout :title="$title">
    <main class="container-fluid min-vh-100 py-5">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-lg-8 col-xxl-6">
                <div class="card border-0 shadow-lg">
                    <div class="card-body">
                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis px-3 py-2">
                                    {{ count($pendingItems) }} pending / {{ count($completedItems) }} completed
                                </span>

                    </div>
                    @if ($pendingItems === [] && $completedItems === [])
                        <div class="rounded-4 border border-dashed text-center py-5 px-4">
                            <h2 class="h5 mb-2">No tasks configured</h2>
                            <p class="text-body-secondary mb-0">Add <code>pending[]</code> or
                                <code>completed[]</code> query params to seed the preview.
                            </p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @forelse ($pendingItems as $item)
                                <div class="list-group-item">
                                    <div class="d-flex align-items-start gap-3">
                                                    <span
                                                        class="badge rounded-pill bg-primary-subtle text-primary-emphasis mt-1">Next</span>
                                        <div class="fw-semibold">{{ $item }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="list-group-item text-body-secondary">
                                    No pending items.</div>
                            @endforelse
                            @foreach ($completedItems as $item)
                                <div class="list-group-item">
                                    <div class="d-flex align-items-start gap-3">
                                                <span
                                                    class="badge rounded-pill bg-success-subtle text-success-emphasis mt-1">Done</span>
                                        <div class="fw-semibold">{{ $item }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
</x-local-tooling-layout>
