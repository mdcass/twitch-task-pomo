<x-local-tooling-layout title="Spotify Now Playing">
    @push('head')
        @livewireStyles
    @endpush

    <main class="container-fluid min-vh-100 py-5">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-12 col-lg-7 col-xxl-4">
                @livewire('local-widgets.spotify-now-playing')
            </div>
        </div>
    </main>

    @push('scripts')
        @livewireScripts
    @endpush
</x-local-tooling-layout>
