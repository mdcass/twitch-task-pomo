<?php

namespace App\Livewire\Canvases;

use App\Actions\Widgets\UpdateWidget;
use App\Livewire\Concerns\InteractsWithOverlays;
use App\Models\Widget;
use App\Support\Widgets\WidgetDefinitionRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EditSharedWidgetForm extends Component
{
    use AuthorizesRequests;
    use InteractsWithOverlays;

    #[Locked]
    public string $offcanvasId = '';

    #[Locked]
    public ?int $widgetId = null;

    public array $fields = [
        'name' => '',
        'config' => [],
        'appearance' => [],
    ];

    public function mount(string $offcanvasId = ''): void
    {
        $this->offcanvasId = $offcanvasId;
    }

    public function loaded(array $payload = []): void
    {
        $this->widgetId = isset($payload['data']['widgetId']) ? (int) $payload['data']['widgetId'] : null;

        if ($this->widgetId === null) {
            return;
        }

        $widget = $this->widget;
        $this->authorize('update', $widget);

        $this->fields = [
            'name' => $widget->name,
            'config' => $widget->config ?? [],
            'appearance' => $widget->appearance ?? [],
        ];

        if ($widget->type->value === 'task_list') {
            $this->fields['config']['pending_text'] = implode("\n", $widget->config['pending'] ?? []);
            $this->fields['config']['completed_text'] = implode("\n", $widget->config['completed'] ?? []);
        }
    }

    public function submit(UpdateWidget $updateWidget): void
    {
        $fields = $this->fields;

        if ($this->widget->type->value === 'task_list') {
            $fields['config']['pending'] = $this->normalizeMultilineList($fields['config']['pending_text'] ?? null);
            $fields['config']['completed'] = $this->normalizeMultilineList($fields['config']['completed_text'] ?? null);
            unset($fields['config']['pending_text'], $fields['config']['completed_text']);
        }

        $widget = $updateWidget->update(auth()->user(), $this->widget, $fields);

        $this->dispatch('widget-updated', widgetId: $widget->id);
        $this->closeOverlay('offcanvas', $this->offcanvasId);
    }

    public function render(): View
    {
        return view('livewire.canvases.edit-shared-widget-form', [
            'definition' => $this->widgetId ? app(WidgetDefinitionRegistry::class)->forType($this->widget->type) : null,
            'widget' => $this->widgetId ? $this->widget : null,
        ]);
    }

    public function getWidgetProperty(): Widget
    {
        return auth()->user()
            ->currentTeam
            ->widgets()
            ->with('followerGoalState')
            ->findOrFail($this->widgetId);
    }

    /**
     * @return list<string>
     */
    private function normalizeMultilineList(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
