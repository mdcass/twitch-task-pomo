<div wire:poll.15s="refreshPlayback" class="card border-0 shadow-lg">
    <div class="card-body">
        <div class="row g-4 align-items-center">
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
                    <div class="small text-uppercase fw-bold text-success mb-2">Now Playing</div>
                    <div class="h3 mb-2">{{ $track['title'] }}</div>
                    <div class="text-body-secondary fs-7">{{ $track['artists_label'] }}</div>
                @elseif ($status === 'idle')
                    <div class="small text-uppercase fw-bold text-warning mb-2">Idle</div>
                    <div class="h4 mb-2">Nothing playing</div>
                    <div class="text-body-secondary">Start playback in Spotify and this widget will update on the next
                        poll.</div>
                @else
                    <div class="small text-uppercase fw-bold text-danger mb-2">Reconnect Required</div>
                    <div class="h4 mb-2">Spotify preview unavailable</div>
                    <div class="text-body-secondary">Reconnect Spotify from the launcher, then reload the widget.</div>
                @endif

                @if ($source !== null)
                    <div class="mt-4 small text-body-tertiary">
                        Source: {{ $source['display_name'] }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
