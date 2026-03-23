<?php

namespace App\Livewire\Canvases;

use App\Actions\Canvases\ArchiveCanvas;
use App\Actions\Canvases\RestoreCanvas;
use App\Livewire\Concerns\InteractsWithOverlays;
use App\Models\Canvas;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CanvasLifecycleModal extends Component
{
    use AuthorizesRequests;
    use InteractsWithOverlays;

    #[Locked]
    public string $modalId = '';

    #[Locked]
    public ?string $redirectTo = null;

    public string $action = 'archive';

    public ?int $canvasId = null;

    public function mount(
        string $action = 'archive',
        ?int $canvasId = null,
        ?string $redirectTo = null,
        string $modalId = '',
    ): void {
        $this->action = $action === 'restore' ? 'restore' : 'archive';
        $this->canvasId = $canvasId;
        $this->redirectTo = $redirectTo;
        $this->modalId = $modalId;
    }

    public function confirm(ArchiveCanvas $archiveCanvas, RestoreCanvas $restoreCanvas)
    {
        $canvas = $this->canvas;
        $user = auth()->user();

        if ($this->action === 'restore') {
            $this->authorize('restore', $canvas);
            $restoreCanvas->restore($user, $canvas);
        } else {
            $this->authorize('delete', $canvas);
            $archiveCanvas->archive($user, $canvas);
        }

        if (is_string($this->redirectTo) && $this->redirectTo !== '') {
            return $this->redirect($this->redirectTo, navigate: true);
        }

        $this->dispatch('canvas-saved');
        $this->closeOverlay('modal', $this->modalId);

        return null;
    }

    public function cancel(): void
    {
        $this->closeOverlay('modal', $this->modalId);
    }

    #[Computed]
    public function canvas(): Canvas
    {
        return auth()->user()
            ->currentTeam
            ->canvases()
            ->withTrashed()
            ->findOrFail($this->canvasId);
    }

    public function render(): View
    {
        return view('livewire.canvases.canvas-lifecycle-modal');
    }
}
