<?php

namespace App\Livewire\LocalWidgets;

use App\Actions\LocalWidgets\SpotifyWidgetService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SpotifyNowPlaying extends Component
{
    public string $status = 'idle';

    public string $message = 'Loading Spotify preview...';

    /**
     * @var array{title:?string,artists:list<string>,artists_label:?string,album_art_url:?string}
     */
    public array $track = [
        'title' => null,
        'artists' => [],
        'artists_label' => null,
        'album_art_url' => null,
    ];

    /**
     * @var array{display_name:string,provider_user_id:string}|null
     */
    public ?array $source = null;

    public function mount(SpotifyWidgetService $spotify): void
    {
        $this->syncFrom($spotify->publicPlaybackPayload());
    }

    public function refreshPlayback(SpotifyWidgetService $spotify): void
    {
        $this->syncFrom($spotify->publicPlaybackPayload());
    }

    public function render(): View
    {
        return view('livewire.local-widgets.spotify-now-playing');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncFrom(array $payload): void
    {
        $this->status = (string) ($payload['status'] ?? 'error');
        $this->message = (string) ($payload['message'] ?? 'Spotify preview is unavailable right now.');
        $this->track = is_array($payload['track'] ?? null) ? $payload['track'] : $this->track;
        $this->source = is_array($payload['source'] ?? null) ? $payload['source'] : null;
    }
}
