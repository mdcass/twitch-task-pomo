<?php

namespace App\Livewire\Concerns;

trait InteractsWithOverlays
{
    protected function dispatchOverlay(
        string $surface,
        string $action,
        string $id,
        array $data = [],
        ?string $title = null,
    ): void {
        $detail = ['id' => $id];

        if (is_string($title) && $title !== '') {
            $detail['title'] = $title;
        }

        if ($data !== []) {
            $detail['data'] = $data;
        }

        $this->dispatch("overlay-{$surface}-{$action}", ...$detail);
    }

    protected function openOverlay(
        string $surface,
        string $id,
        array $data = [],
        ?string $title = null,
        ?string $action = null,
    ): void {
        $this->dispatchOverlay(
            surface: $surface,
            action: $action ?? ($data !== [] ? 'load' : 'open'),
            id: $id,
            data: $data,
            title: $title,
        );
    }

    protected function closeOverlay(string $surface, string $id): void
    {
        $this->dispatchOverlay(
            surface: $surface,
            action: 'close',
            id: $id,
        );
    }
}
