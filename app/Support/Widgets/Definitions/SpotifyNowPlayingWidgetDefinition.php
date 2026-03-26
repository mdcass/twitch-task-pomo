<?php

namespace App\Support\Widgets\Definitions;

use App\Enums\ExternalAuthProvider;
use App\Enums\Models\WidgetType;

class SpotifyNowPlayingWidgetDefinition extends AbstractWidgetDefinition
{
    public function type(): WidgetType
    {
        return WidgetType::SpotifyNowPlaying;
    }

    public function defaultConfig(): array
    {
        return [
            'title' => 'Now Playing',
            'show_album_art' => true,
        ];
    }

    public function configRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'show_album_art' => ['required', 'boolean'],
        ];
    }

    public function defaultAppearance(): array
    {
        return [
            'title_alignment' => 'left',
            'accent' => 'info',
        ];
    }

    public function editorView(): string
    {
        return 'widgets.editor.spotify-now-playing';
    }

    public function renderView(): string
    {
        return 'overlay.widgets.spotify-now-playing';
    }

    public function requiredProvider(): ?ExternalAuthProvider
    {
        return ExternalAuthProvider::Spotify;
    }

    public function artboard(): array
    {
        return ['width' => 720, 'height' => 240];
    }
}
