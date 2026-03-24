<?php

namespace App\Livewire\Canvases;

use App\Actions\WidgetInstances\CreateBuiltInWidget;
use App\Enums\Models\WidgetType;
use App\Livewire\Concerns\InteractsWithOverlays;
use App\Models\Canvas;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AddBuiltInWidgetForm extends Component
{
    use AuthorizesRequests;
    use InteractsWithOverlays;

    #[Locked]
    public int $canvasId;

    #[Locked]
    public string $offcanvasId = '';

    public array $fields = [
        'type' => 'task_list',
    ];

    public function mount(int $canvasId, string $offcanvasId = ''): void
    {
        $this->canvasId = $canvasId;
        $this->offcanvasId = $offcanvasId;
    }

    public function submit(CreateBuiltInWidget $createBuiltInWidget): void
    {
        $canvas = $this->resolveCanvas();

        $this->authorize('update', $canvas);

        $validated = $this->validate()['fields'];
        $widget = $createBuiltInWidget->create(
            auth()->user(),
            $canvas,
            WidgetType::from($validated['type']),
        );

        $this->dispatch('widget-created', widgetId: $widget->id);
        $this->closeOverlay('offcanvas', $this->offcanvasId);
    }

    public function cancel(): void
    {
        $this->closeOverlay('offcanvas', $this->offcanvasId);
    }

    public function render(): View
    {
        return view('livewire.canvases.add-built-in-widget-form', [
            'widgetTypes' => WidgetType::cases(),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'fields.type' => ['required', 'in:'.implode(',', WidgetType::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'fields.type' => 'widget type',
        ];
    }

    protected function resolveCanvas(): Canvas
    {
        return auth()->user()
            ->currentTeam
            ->canvases()
            ->findOrFail($this->canvasId);
    }
}
