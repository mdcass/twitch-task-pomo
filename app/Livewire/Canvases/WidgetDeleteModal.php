<?php

namespace App\Livewire\Canvases;

use App\Actions\WidgetInstances\DeleteWidgetInstance;
use App\Livewire\Concerns\InteractsWithOverlays;
use App\Models\WidgetInstance;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WidgetDeleteModal extends Component
{
    use AuthorizesRequests;
    use InteractsWithOverlays;

    #[Locked]
    public string $modalId = '';

    public ?int $widgetId = null;

    public function mount(?int $widgetId = null, string $modalId = ''): void
    {
        $this->widgetId = $widgetId;
        $this->modalId = $modalId;
    }

    public function confirm(DeleteWidgetInstance $deleteWidgetInstance): void
    {
        $widget = $this->widget;

        $this->authorize('update', $widget->canvas);

        $selectedWidgetId = $deleteWidgetInstance->delete(auth()->user(), $widget);

        $this->dispatch('widget-deleted', deletedWidgetId: $widget->id, selectedWidgetId: $selectedWidgetId)
            ->to(CanvasComposer::class);
        $this->dispatch('widget-deleted-browser', deletedWidgetId: $widget->id, selectedWidgetId: $selectedWidgetId);
        $this->closeOverlay('modal', $this->modalId);
    }

    public function cancel(): void
    {
        $this->closeOverlay('modal', $this->modalId);
    }

    #[Computed]
    public function widget(): WidgetInstance
    {
        return auth()->user()
            ->currentTeam
            ->widgetInstances()
            ->with('canvas')
            ->findOrFail($this->widgetId);
    }

    public function render(): View
    {
        return view('livewire.canvases.widget-delete-modal');
    }
}
