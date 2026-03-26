<?php

namespace App\Livewire\Canvases;

use App\Actions\WidgetInstances\AttachWidgetToCanvas;
use App\Livewire\Concerns\InteractsWithOverlays;
use App\Models\Canvas;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AttachExistingWidgetForm extends Component
{
    use AuthorizesRequests;
    use InteractsWithOverlays;

    #[Locked]
    public int $canvasId;

    #[Locked]
    public string $offcanvasId = '';

    public array $fields = [
        'widget_id' => null,
    ];

    public function mount(int $canvasId, string $offcanvasId = ''): void
    {
        $this->canvasId = $canvasId;
        $this->offcanvasId = $offcanvasId;
    }

    public function submit(AttachWidgetToCanvas $attachWidgetToCanvas): void
    {
        $canvas = $this->resolveCanvas();
        $this->authorize('update', $canvas);

        $validated = $this->validate()['fields'];
        $widget = auth()->user()->currentTeam->widgets()->findOrFail((int) $validated['widget_id']);
        $widgetInstance = $attachWidgetToCanvas->attach(auth()->user(), $canvas, $widget);

        $this->dispatch('widget-created', widgetId: $widgetInstance->id);
        $this->closeOverlay('offcanvas', $this->offcanvasId);
    }

    public function render(): View
    {
        return view('livewire.canvases.attach-existing-widget-form', [
            'widgets' => auth()->user()->currentTeam->widgets()->latest('updated_at')->get(),
        ]);
    }

    protected function rules(): array
    {
        return [
            'fields.widget_id' => ['required', 'integer'],
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
