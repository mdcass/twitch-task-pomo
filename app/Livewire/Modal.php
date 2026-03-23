<?php

namespace App\Livewire;

use Illuminate\Support\Arr;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Modal extends Component
{
    private static int $count = 0;

    #[Locked]
    public string $elementId;

    #[Locked]
    public ?string $componentName = null;

    #[Locked]
    public array $componentProps = [];

    #[Locked]
    public ?string $templateName = null;

    #[Locked]
    public array $templateProps = [];

    #[Locked]
    public string $maxWidth = '2xl';

    #[Locked]
    public bool $dismissible = true;

    #[Locked]
    public ?string $defaultTitle = null;

    #[Locked]
    public ?string $initialFocus = null;

    #[Locked]
    public string $initialFocusMethod = 'focus';

    public bool $open = false;

    public bool $readyToLoad = false;

    public int $loadIteration = 0;

    public array $modalData = [];

    public ?string $title = null;

    public function mount(
        string|array|null $component = null,
        string|array|null $template = null,
        ?string $elementId = null,
        string $maxWidth = '2xl',
        bool $dismissible = true,
        ?string $title = null,
        ?string $initialFocus = null,
        string $initialFocusMethod = 'focus',
        bool $open = false,
    ): void {
        $this->elementId = $elementId ?: 'modal-'.++self::$count;
        $this->maxWidth = $maxWidth;
        $this->dismissible = $dismissible;
        $this->defaultTitle = $title;
        $this->title = $title;
        $this->initialFocus = $initialFocus;
        $this->initialFocusMethod = $initialFocusMethod;
        $this->open = $open;

        if ($component !== null) {
            $this->componentName = is_array($component) ? $component[0] : $component;
            $this->componentProps = is_array($component) ? ($component[1] ?? []) : [];
        }

        if ($template !== null) {
            $this->templateName = is_array($template) ? $template[0] : $template;
            $this->templateProps = is_array($template) ? ($template[1] ?? []) : [];
        }

        if ($this->open && ($this->componentName !== null || $this->templateName !== null)) {
            $this->readyToLoad = true;
            $this->loadIteration = 1;
        }
    }

    public function render()
    {
        return view('livewire.modal');
    }

    public function loaded(array $payload = []): void
    {
        if (! $this->matchesPayload($payload)) {
            return;
        }

        $this->title = $this->resolveTitle($payload);
        $this->modalData = $this->extractModalData($payload);
        $this->readyToLoad = true;
        $this->open = true;
        $this->loadIteration++;
    }

    public function openFromEvent(array $payload = []): void
    {
        if (! $this->matchesPayload($payload)) {
            return;
        }

        if ($this->hasConfiguredContent()) {
            $this->title = $this->resolveTitle($payload);

            if ($this->payloadContainsData($payload)) {
                $this->modalData = $this->extractModalData($payload);
            }

            if (! $this->readyToLoad || $this->payloadContainsData($payload)) {
                $this->readyToLoad = true;
                $this->loadIteration++;
            }
        }

        $this->open = true;
    }

    public function closeFromEvent(array $payload = []): void
    {
        if (! $this->matchesPayload($payload)) {
            return;
        }

        $this->open = false;
    }

    public function updatedOpen(bool $value): void
    {
        if ($value) {
            return;
        }

        $this->readyToLoad = false;
        $this->modalData = [];
        $this->title = $this->defaultTitle;
    }

    public function getChildKeyProperty(): string
    {
        return implode('-', [
            $this->elementId,
            $this->loadIteration,
            md5(json_encode($this->modalData, JSON_THROW_ON_ERROR)),
        ]);
    }

    public function getResolvedComponentPropsProperty(): array
    {
        return array_merge(
            $this->componentProps,
            $this->modalData,
            [
                'modalId' => $this->elementId,
                'modalData' => $this->modalData,
            ],
        );
    }

    public function getResolvedTemplatePropsProperty(): array
    {
        return array_merge(
            $this->templateProps,
            $this->modalData,
            [
                'modalId' => $this->elementId,
                'modalData' => $this->modalData,
            ],
        );
    }

    protected function extractModalData(array $payload): array
    {
        $data = $payload['data'] ?? Arr::except($payload, ['id', 'title']);

        return is_array($data) ? $data : [];
    }

    protected function hasConfiguredContent(): bool
    {
        return $this->componentName !== null || $this->templateName !== null;
    }

    protected function matchesPayload(array $payload): bool
    {
        return ($payload['id'] ?? null) === $this->elementId;
    }

    protected function payloadContainsData(array $payload): bool
    {
        return $this->extractModalData($payload) !== [];
    }

    protected function resolveTitle(array $payload): ?string
    {
        return is_string($payload['title'] ?? null) ? $payload['title'] : $this->defaultTitle;
    }
}
