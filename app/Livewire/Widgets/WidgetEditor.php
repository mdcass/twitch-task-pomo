<?php

namespace App\Livewire\Widgets;

use App\Actions\Widgets\SetWidgetArchivedState;
use App\Actions\Widgets\UpdateFollowerGoalState;
use App\Actions\Widgets\UpdateWidget;
use App\Actions\Widgets\UpdateWidgetPublication;
use App\Enums\Models\WidgetType;
use App\Models\Widget;
use App\Support\Routing\OriginUrlGenerator;
use App\Support\Integrations\TeamProviderAuthResolver;
use App\Support\Widgets\WidgetDefinitionRegistry;
use App\Support\Widgets\WidgetRenderDataFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

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
            $this->fields['config']['pending_text'] = implode("\n", $widget->config['pending'] ?? []);
            $this->fields['config']['completed_text'] = implode("\n", $widget->config['completed'] ?? []);
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
            $this->fields['config']['pending_text'] = implode("\n", $widget->config['pending'] ?? []);
            $this->fields['config']['completed_text'] = implode("\n", $widget->config['completed'] ?? []);
        }
    }

    public function publish(UpdateWidgetPublication $publication): void
    {
        $publication->publish(auth()->user(), $this->widget);
        unset($this->widget);
        session()->flash('status', 'Widget published.');
    }

    public function unpublish(UpdateWidgetPublication $publication): void
    {
        $publication->unpublish(auth()->user(), $this->widget);
        unset($this->widget);
        session()->flash('status', 'Widget unpublished.');
    }

    public function regenerate(UpdateWidgetPublication $publication): void
    {
        $publication->regenerate(auth()->user(), $this->widget);
        unset($this->widget);
        session()->flash('status', 'Widget URL regenerated.');
    }

    public function archive(SetWidgetArchivedState $archiveState): void
    {
        $archiveState->archive(auth()->user(), $this->widget);
        unset($this->widget);
        session()->flash('status', 'Widget archived.');
    }

    public function restore(SetWidgetArchivedState $archiveState): void
    {
        $archiveState->restore(auth()->user(), $this->widget);
        unset($this->widget);
        session()->flash('status', 'Widget restored.');
    }

    public function resetFollowerGoal(UpdateFollowerGoalState $followerGoalState): void
    {
        abort_unless($this->widget->type === WidgetType::FollowerGoal, 404);

        $followerGoalState->reset(auth()->user(), $this->widget);
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
            ->findOrFail($this->widgetId);
    }

    #[Computed]
    public function definition()
    {
        return app(WidgetDefinitionRegistry::class)->forType($this->widget->type);
    }

    #[Computed]
    public function previewData(): array
    {
        $widget = $this->widget->replicate();
        $widget->config = $this->normalizedFields()['config'] ?? [];
        $widget->appearance = $this->normalizedFields()['appearance'] ?? [];
        $widget->setRelation('team', $this->widget->team);
        $widget->setRelation('followerGoalState', $this->widget->followerGoalState);

        return app(WidgetRenderDataFactory::class)->forWidget($widget);
    }

    #[Computed]
    public function providerAuth()
    {
        $provider = $this->definition->requiredProvider();

        if ($provider === null) {
            return null;
        }

        return app(TeamProviderAuthResolver::class)->current($this->widget->team, $provider);
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
            $fields['config']['pending'] = $this->normalizeMultilineList($fields['config']['pending_text'] ?? null);
            $fields['config']['completed'] = $this->normalizeMultilineList($fields['config']['completed_text'] ?? null);
            unset($fields['config']['pending_text'], $fields['config']['completed_text']);
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function normalizeMultilineList(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
