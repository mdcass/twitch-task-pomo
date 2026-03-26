<?php

namespace App\Livewire\Widgets;

use App\Actions\Widgets\CreateWidget;
use App\Enums\Models\WidgetType;
use App\Models\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WidgetCreateForm extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public string $offcanvasId = '';

    public array $fields = [
        'type' => 'task_list',
        'name' => '',
    ];

    public function mount(string $offcanvasId = ''): void
    {
        $this->offcanvasId = $offcanvasId;
    }

    public function submit(CreateWidget $createWidget): mixed
    {
        $this->authorize('create', Widget::class);

        $validated = $this->validate()['fields'];
        $widget = $createWidget->create(
            auth()->user(),
            auth()->user()->currentTeam,
            WidgetType::from($validated['type']),
            $validated['name'] !== '' ? $validated['name'] : null,
        );

        return $this->redirect(route('widgets.edit', $widget, false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.widgets.widget-create-form', [
            'widgetTypes' => WidgetType::cases(),
        ]);
    }

    protected function rules(): array
    {
        return [
            'fields.type' => ['required', 'in:'.implode(',', WidgetType::values())],
            'fields.name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
