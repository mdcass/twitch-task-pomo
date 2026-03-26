<div wire:poll.15s="refreshPlayback" class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-sm-4">
                <div class="ratio ratio-1x1 rounded-4 overflow-hidden border bg-body-tertiary">
                    @if (filled($track['album_art_url']))
                        <img src="{{ $track['album_art_url'] }}" alt="" class="w-100 h-100 object-fit-cover"
                            referrerpolicy="no-referrer">
                    @else
                        <div class="d-flex h-100 align-items-center justify-content-center text-body-tertiary">
                            <span class="fa-solid fa-music fs-1"></span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-12 col-sm-8">
                @if ($status === 'playing')
                    <div class="small text-uppercase fw-bold text-success mb-2">{{ __('Now Playing') }}</div>
                    <div class="h4 mb-2">{{ $track['title'] }}</div>
                    <div class="text-body-secondary">{{ $track['artists_label'] }}</div>
                @elseif ($status === 'idle')
                    <div class="small text-uppercase fw-bold text-warning mb-2">{{ __('Idle') }}</div>
                    <div class="h5 mb-2">{{ __('Nothing playing') }}</div>
                    <div class="text-body-secondary">{{ __('Start playback in Spotify and this widget will update on the next poll.') }}</div>
                @else
                    <div class="small text-uppercase fw-bold text-danger mb-2">{{ __('Reconnect Required') }}</div>
                    <div class="h5 mb-2">{{ __('Spotify widget unavailable') }}</div>
                    <div class="text-body-secondary">{{ $message }}</div>
                @endif

                @if ($source !== null)
                    <div class="mt-3 small text-body-tertiary">{{ __('Source: :name', ['name' => $source['display_name']]) }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
