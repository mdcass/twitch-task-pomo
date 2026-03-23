<?php

namespace Tests\Feature\Stubs;

use Livewire\Component;

class OffcanvasChildComponent extends Component
{
    public string $mode = '';

    public int|string|null $recordId = null;

    public string $offcanvasId = '';

    public array $offcanvasData = [];

    public string $label = '';

    public function mount(
        string $mode = '',
        int|string|null $recordId = null,
        string $offcanvasId = '',
        array $offcanvasData = [],
        string $label = '',
    ): void {
        $this->mode = $mode;
        $this->recordId = $recordId;
        $this->offcanvasId = $offcanvasId;
        $this->offcanvasData = $offcanvasData;
        $this->label = $label;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <p>Mode: {{ $mode }}</p>
                <p>Record: {{ $recordId }}</p>
                <p>Offcanvas: {{ $offcanvasId }}</p>
                <p>Label: {{ $label }}</p>
                <p>Payload Name: {{ $offcanvasData['name'] ?? 'none' }}</p>
            </div>
            BLADE;
    }
}
