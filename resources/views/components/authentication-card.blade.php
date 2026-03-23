<x-auth.page-card>
    <x-slot name="aside">
        <div class="d-flex flex-column justify-content-between p-4 p-lg-5 h-100">
            <div>
                <span class="twitch-auth-badge">Phoenix Bootstrap</span>
                <h2 class="display-6 fw-semibold text-body-emphasis mt-4 mb-3">
                    Streaming overlays, built on release-intent primitives.
                </h2>
                <p class="text-body-secondary mb-0">
                    This shell keeps the app on product-owned Blade and Vite assets while using Phoenix as the Bootstrap
                    design source.
                </p>
            </div>

            <div class="pt-5">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="badge badge-phoenix badge-phoenix-success rounded-pill">Livewire</span>
                    <span class="text-body-secondary fw-semibold">Server-driven UI preserved</span>
                </div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="badge badge-phoenix badge-phoenix-primary rounded-pill">Vite</span>
                    <span class="text-body-secondary fw-semibold">Theme compiled from source</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge badge-phoenix badge-phoenix-info rounded-pill">Phase 1</span>
                    <span class="text-body-secondary fw-semibold">Auth and shared shell first</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="mb-5">
        {{ $logo }}
    </div>

    {{ $slot }}
</x-auth.page-card>
