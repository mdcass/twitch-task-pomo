<?php

namespace App\Livewire\Widgets;

use App\Actions\Widgets\UpdateWidget;
use App\Enums\Models\WidgetType;
use App\Models\Widget;
use App\Support\Widgets\Definitions\TaskListWidgetDefinition;
use App\Support\Routing\OriginUrlGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;

class WidgetEditor extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public int $widgetId;

    public array $fields = [
        'name' => '',
        'config' => [],
        'appearance' => [],
    ];

    public function mount(int $widgetId): void
    {
        $this->widgetId = $widgetId;

        $widget = $this->widget;
        $this->authorize('view', $widget);

        $this->fields = [
            'name' => $widget->name,
            'config' => $widget->config ?? [],
            'appearance' => $widget->appearance ?? [],
        ];

        if ($widget->type->value === 'task_list') {
            $this->fields['config'] = TaskListWidgetDefinition::hydrateFieldsForEditor($this->fields['config']);
        }
    }

    public function save(UpdateWidget $updateWidget): void
    {
        $widget = $updateWidget->update(auth()->user(), $this->widget, $this->normalizedFields());

        $this->fields = [
            'name' => $widget->name,
            'config' => $widget->config ?? [],
            'appearance' => $widget->appearance ?? [],
        ];

        if ($widget->type->value === 'task_list') {
            $this->fields['config'] = TaskListWidgetDefinition::hydrateFieldsForEditor($this->fields['config']);
        }
    }

    public function publish(): void
    {
        $this->authorize('update', $this->widget);
        $this->widget->publish();
        unset($this->widget);
        session()->flash('status', 'Standalone URL turned on.');
    }

    public function unpublish(): void
    {
        $this->authorize('update', $this->widget);
        $this->widget->unpublish();
        unset($this->widget);
        session()->flash('status', 'Standalone URL turned off.');
    }

    public function regenerate(): void
    {
        $this->authorize('update', $this->widget);
        $this->widget->regeneratePublicationKey();
        unset($this->widget);
        session()->flash('status', 'Standalone URL regenerated.');
    }

    public function archive(): void
    {
        $this->authorize('delete', $this->widget);
        $this->widget->archive();
        unset($this->widget);
        session()->flash('status', 'Widget archived.');
    }

    public function restore(): void
    {
        $this->authorize('update', $this->widget);
        $this->widget->restore();
        unset($this->widget);
        session()->flash('status', 'Widget restored.');
    }

    public function resetFollowerGoal(): void
    {
        abort_unless($this->widget->type === WidgetType::FollowerGoal, 404);

        $this->authorize('update', $this->widget);
        $this->widget->resetFollowerGoal();
        unset($this->widget);
        session()->flash('status', 'Follower Goal reset.');
    }

    #[Computed]
    public function widget(): Widget
    {
        return auth()->user()
            ->currentTeam
            ->widgets()
            ->with(['createdByUser', 'followerGoalState'])
            ->withCount(['widgetInstances as usage_count'])
            ->findOrFail($this->widgetId);
    }

    #[Computed]
    public function definition()
    {
        return $this->widget->definition();
    }

    #[Computed]
    public function previewData(): array
    {
        $widget = $this->widget->replicate();
        $widget->config = $this->normalizedFields()['config'] ?? [];
        $widget->appearance = $this->normalizedFields()['appearance'] ?? [];
        $widget->setRelation('team', $this->widget->team);
        $widget->setRelation('followerGoalState', $this->widget->followerGoalState);

        return $widget->definition()->renderData($widget);
    }

    #[Computed]
    public function providerAuth()
    {
        if (! $this->definition->requiresProviderConnection()) {
            return null;
        }

        $provider = $this->definition->requiredProvider()
            ?? throw new LogicException(sprintf(
                'Widget definition [%s] requires a provider connection but does not declare a provider.',
                $this->definition->type()->value,
            ));

        return $this->widget->team->currentProviderAuth($provider);
    }

    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()?->can('update', $this->widget) ?? false;
    }

    #[Computed]
    public function publishedUrl(): ?string
    {
        if (! $this->widget->isPublished()) {
            return null;
        }

        return app(OriginUrlGenerator::class)->signedOverlayRoute('overlay.widgets.published', [
            'widget' => $this->widget->uuid,
            'key' => $this->widget->publication_key,
        ]);
    }

    public function render(): View
    {
        return view('livewire.widgets.widget-editor');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedFields(): array
    {
        $fields = $this->fields;

        if ($this->widget->type->value === 'task_list') {
            $fields['config'] = TaskListWidgetDefinition::normalizeEditorFields($fields['config']);
        }

        return $fields;
    }
}
