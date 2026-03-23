<?php

namespace App\Livewire\Canvases;

use App\Actions\Canvases\CreateCanvas;
use App\Actions\Canvases\UpdateCanvas;
use App\Livewire\Concerns\InteractsWithOverlays;
use App\Models\Canvas;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CanvasForm extends Component
{
    use AuthorizesRequests;
    use InteractsWithOverlays;

    #[Locked]
    public string $mode = 'create';

    #[Locked]
    public ?string $redirectTo = null;

    #[Locked]
    public string $offcanvasId = '';

    public ?int $canvasId = null;

    public array $fields = [
        'name' => '',
        'width' => 1920,
        'height' => 1080,
    ];

    public function mount(
        string $mode = 'create',
        ?int $canvasId = null,
        ?string $redirectTo = null,
        string $offcanvasId = '',
    ): void {
        $this->mode = in_array($mode, ['create', 'edit'], true) ? $mode : 'create';
        $this->canvasId = $canvasId;
        $this->redirectTo = $redirectTo;
        $this->offcanvasId = $offcanvasId;

        if ($this->mode === 'edit' && $this->canvasId !== null) {
            $canvas = $this->resolveCanvas();

            $this->fields = [
                'name' => $canvas->name,
                'width' => $canvas->width,
                'height' => $canvas->height,
            ];
        }
    }

    public function submit(CreateCanvas $createCanvas, UpdateCanvas $updateCanvas)
    {
        $user = auth()->user();
        $validated = $this->validate()['fields'];
        $validated['name'] = trim((string) $validated['name']);
        $validated['width'] = (int) $validated['width'];
        $validated['height'] = (int) $validated['height'];

        if ($this->mode === 'create') {
            $this->authorize('create', Canvas::class);

            $canvas = $createCanvas->create($user, $validated);

            return $this->redirectRoute('canvases.edit', ['canvas' => $canvas], navigate: true);
        }

        $canvas = $this->resolveCanvas();

        $this->authorize('update', $canvas);
        $canvas = $updateCanvas->update($user, $canvas, $validated);

        if (is_string($this->redirectTo) && $this->redirectTo !== '') {
            return $this->redirect($this->redirectTo, navigate: true);
        }

        $this->dispatch('canvas-saved');
        $this->closeOverlay('offcanvas', $this->offcanvasId);

        return null;
    }

    public function cancel(): void
    {
        $this->closeOverlay('offcanvas', $this->offcanvasId);
    }

    public function render(): View
    {
        return view('livewire.canvases.canvas-form');
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function rules(): array
    {
        return [
            'fields.name' => ['required', 'string', 'max:255'],
            'fields.width' => ['required', 'integer', 'min:1'],
            'fields.height' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'fields.name' => 'name',
            'fields.width' => 'width',
            'fields.height' => 'height',
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
