<?php

namespace App\Livewire\Widgets;

use App\Actions\Integrations\SpotifyPlaybackService;
use App\Enums\ExternalAuthProvider;
use App\Models\Widget;
use App\Support\Integrations\TeamProviderAuthResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SpotifyNowPlaying extends Component
{
    #[Locked]
    public int $widgetId;

    public string $status = 'idle';

    public string $message = 'Loading Spotify preview...';

    public array $track = [
        'title' => null,
        'artists' => [],
        'artists_label' => null,
        'album_art_url' => null,
    ];

    public ?array $source = null;

    public function mount(int $widgetId, SpotifyPlaybackService $spotify): void
    {
        $this->widgetId = $widgetId;
        $this->sync($spotify);
    }

    public function refreshPlayback(SpotifyPlaybackService $spotify): void
    {
        $this->sync($spotify);
    }

    public function render(): View
    {
        return view('livewire.widgets.spotify-now-playing');
    }

    private function sync(SpotifyPlaybackService $spotify): void
    {
        $widget = Widget::query()->with('team')->find($this->widgetId);
        $providerAuth = $widget
            ? app(TeamProviderAuthResolver::class)->current($widget->team, ExternalAuthProvider::Spotify)
            : null;
        $payload = $spotify->payloadForAuth($providerAuth);

        $this->status = (string) ($payload['status'] ?? 'error');
        $this->message = (string) ($payload['message'] ?? 'Spotify preview is unavailable right now.');
        $this->track = is_array($payload['track'] ?? null) ? $payload['track'] : $this->track;
        $this->source = is_array($payload['source'] ?? null) ? $payload['source'] : null;
    }
}
