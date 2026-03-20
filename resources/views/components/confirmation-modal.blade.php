@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="modal-body">
        <div class="d-flex align-items-start gap-3">
            <div class="d-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger shrink-0" style="width: 3rem; height: 3rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>

            <div>
                <h3 class="h5 mb-3">
                    {{ $title }}
                </h3>

                <div class="text-body-secondary">
                    {{ $content }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        {{ $footer }}
    </div>
</x-modal>
