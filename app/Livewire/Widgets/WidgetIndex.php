<?php

namespace App\Livewire\Widgets;

use App\Actions\Widgets\CreateWidget;
use App\Enums\Models\WidgetLifecycleState;
use App\Enums\Models\WidgetType;
use App\Models\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class WidgetIndex extends Component
{
    use AuthorizesRequests;

    public array $fields = [
        'type' => 'task_list',
        'name' => '',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $lifecycle = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Widget::class);
    }

    public function create(CreateWidget $createWidget): mixed
    {
        $this->authorize('create', Widget::class);

        $validated = $this->validate()['fields'];
        $widget = $createWidget->create(
            auth()->user(),
            auth()->user()->currentTeam,
            WidgetType::from($validated['type']),
            $validated['name'] !== '' ? $validated['name'] : null,
        );

        session()->flash('status', 'Widget created.');

        return $this->redirect(route('widgets.edit', $widget, false), navigate: true);
    }

    #[Computed]
    public function canCreate(): bool
    {
        return auth()->user()?->can('create', Widget::class) ?? false;
    }

    /**
     * @return Collection<int, Widget>
     */
    #[Computed]
    public function widgets(): Collection
    {
        return auth()->user()
            ->currentTeam
            ->widgets()
            ->withCount(['widgetInstances as usage_count'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->lifecycle !== '', fn ($query) => $query->where('lifecycle_state', $this->lifecycle))
            ->latest('updated_at')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.widgets.widget-index', [
            'widgetTypes' => WidgetType::cases(),
            'lifecycleStates' => array_values(array_filter(
                WidgetLifecycleState::cases(),
                static fn (WidgetLifecycleState $state): bool => $state !== WidgetLifecycleState::Draft,
            )),
        ]);
    }

    protected function rules(): array
    {
        return [
            'fields.type' => ['required', 'in:'.implode(',', WidgetType::values())],
            'fields.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'fields.type' => 'widget type',
            'fields.name' => 'name',
        ];
    }
}
