<?php

namespace App\Livewire\Canvases;

use App\Actions\WidgetInstances\CreateRemoteWidget;
use App\Livewire\Concerns\InteractsWithOverlays;
use App\Models\Canvas;
use App\Support\Widgets\RemoteWidgetUrlGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AddRemoteWidgetForm extends Component
{
    use AuthorizesRequests;
    use InteractsWithOverlays;

    #[Locked]
    public int $canvasId;

    #[Locked]
    public string $offcanvasId = '';

    public array $fields = [
        'name' => '',
        'embed_url' => '',
    ];

    public function mount(int $canvasId, string $offcanvasId = ''): void
    {
        $this->canvasId = $canvasId;
        $this->offcanvasId = $offcanvasId;
    }

    public function submit(CreateRemoteWidget $createRemoteWidget): void
    {
        $canvas = $this->resolveCanvas();

        $this->authorize('update', $canvas);

        $validated = $this->validate()['fields'];
        $widget = $createRemoteWidget->create(auth()->user(), $canvas, $validated);

        $this->dispatch('widget-created', widgetId: $widget->id);
        $this->closeOverlay('offcanvas', $this->offcanvasId);
    }

    public function cancel(): void
    {
        $this->closeOverlay('offcanvas', $this->offcanvasId);
    }

    public function render(): View
    {
        return view('livewire.canvases.add-remote-widget-form');
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function rules(): array
    {
        return [
            'fields.name' => ['nullable', 'string', 'max:255'],
            'fields.embed_url' => [
                'required',
                'string',
                'max:2048',
                'url',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || parse_url($value, PHP_URL_SCHEME) !== 'https') {
                        $fail('The embed URL must be a valid HTTPS URL.');
                        return;
                    }

                    $message = app(RemoteWidgetUrlGuard::class)->check($value);

                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'fields.name' => 'name',
            'fields.embed_url' => 'embed URL',
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
