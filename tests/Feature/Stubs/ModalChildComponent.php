<?php

namespace Tests\Feature\Stubs;

use Livewire\Component;

class ModalChildComponent extends Component
{
    public string $mode = 'create';

    public ?int $recordId = null;

    public string $modalId = '';

    public array $modalData = [];

    public ?string $label = null;

    public function mount(
        string $mode = 'create',
        ?int $recordId = null,
        string $modalId = '',
        array $modalData = [],
        ?string $label = null,
    ): void {
        $this->mode = $mode;
        $this->recordId = $recordId;
        $this->modalId = $modalId;
        $this->modalData = $modalData;
        $this->label = $label;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <p>Mode: {{ $mode }}</p>
                <p>Record: {{ $recordId ?? 'none' }}</p>
                <p>Modal: {{ $modalId }}</p>
                <p>Label: {{ $label ?? 'none' }}</p>
                <p>Payload Name: {{ $modalData['name'] ?? 'none' }}</p>
            </div>
            BLADE;
    }
}
