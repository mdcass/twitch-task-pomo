<?php

namespace App\Livewire\Canvases;

use App\Livewire\Concerns\InteractsWithOverlays;
use App\Models\Canvas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CanvasIndex extends Component
{
    use AuthorizesRequests;
    use InteractsWithOverlays;

    public string $filter = 'active';

    public function mount(): void
    {
        $this->authorize('viewAny', Canvas::class);
    }

    #[On('canvas-saved')]
    public function refreshCanvases(): void
    {
        //
    }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, ['active', 'archived', 'all'], true)) {
            return;
        }

        $this->filter = $filter;
    }

    public function openEditOffcanvas(int $canvasId): void
    {
        $canvas = $this->currentTeamCanvasQuery()->findOrFail($canvasId);

        $this->authorize('update', $canvas);

        $this->openOverlay(
            surface: 'offcanvas',
            id: 'canvas-edit-offcanvas',
            data: ['canvasId' => $canvas->id],
            title: __('Edit Canvas'),
        );
    }

    public function confirmLifecycleAction(int $canvasId, string $action): void
    {
        $canvas = $this->currentTeamCanvasQuery(withTrashed: true)->findOrFail($canvasId);

        if ($action === 'restore') {
            $this->authorize('restore', $canvas);
        } else {
            $this->authorize('delete', $canvas);
        }

        $this->openOverlay(
            surface: 'modal',
            id: 'canvas-lifecycle-modal',
            data: [
                'canvasId' => $canvas->id,
                'action' => $action,
            ],
            title: $action === 'restore' ? __('Restore Canvas') : __('Archive Canvas'),
        );
    }

    #[Computed]
    public function canCreate(): bool
    {
        return auth()->user()?->can('create', Canvas::class) ?? false;
    }

    #[Computed]
    public function recentCanvases(): Collection
    {
        return $this->currentTeamCanvasQuery()
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }

    #[Computed]
    public function canvases(): Collection
    {
        return $this->applyFilter($this->currentTeamCanvasQuery(withTrashed: $this->filter !== 'active'))
            ->latest('updated_at')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.canvases.canvas-index');
    }

    protected function currentTeamCanvasQuery(bool $withTrashed = false): Builder
    {
        $query = auth()->user()
            ->currentTeam
            ->canvases()
            ->getQuery();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query;
    }

    protected function applyFilter(Builder $query): Builder
    {
        return match ($this->filter) {
            'archived' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
            default => $query,
        };
    }
}
