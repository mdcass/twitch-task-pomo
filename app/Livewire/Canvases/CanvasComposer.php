<?php

namespace App\Livewire\Canvases;

use App\Actions\WidgetInstances\ReorderWidgetInstance;
use App\Actions\WidgetInstances\RestoreCanvasWidgetState;
use App\Actions\WidgetInstances\ToggleWidgetVisibility;
use App\Actions\WidgetInstances\UpdateWidgetGeometry;
use App\Models\Canvas;
use App\Models\WidgetInstance;
use App\Support\Widgets\OverlayWidgetFrameUrlFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class CanvasComposer extends Component
{
    use AuthorizesRequests;

    private const MIN_WIDTH = 120;
    private const MIN_HEIGHT = 90;

    #[Locked]
    public int $canvasId;

    public ?int $selectedWidgetId = null;

    /**
     * @var array<int, array{ends_at?:string}>
     */
    public array $previewSeeds = [];

    public function mount(int $canvasId): void
    {
        $this->canvasId = $canvasId;

        $canvas = $this->resolveCanvas();

        $this->authorize('view', $canvas);

        $this->selectedWidgetId = $canvas->orderedWidgetInstances()->value('id');
        $this->syncPreviewSeeds();
    }

    public function hydrate(): void
    {
        $this->syncPreviewSeeds();
    }

    public function selectWidget(int $widgetId): void
    {
        if (! $this->widgetExists($widgetId)) {
            return;
        }

        $this->selectedWidgetId = $widgetId;
    }

    public function saveGeometry(int $widgetId, array $geometry, UpdateWidgetGeometry $updateWidgetGeometry): void
    {
        $widget = $this->resolveWidget($widgetId);

        $updateWidgetGeometry->update(auth()->user(), $widget, $geometry);
        $this->refreshComputedState();
    }

    public function resetGeometry(int $widgetId, string $scope, UpdateWidgetGeometry $updateWidgetGeometry): void
    {
        $widget = $this->resolveWidget($widgetId);
        $geometry = match ($scope) {
            'crop' => $this->cropResetGeometry($widget),
            'source' => $this->sourceResetGeometry($widget),
            'aspect' => $this->aspectResetGeometry($widget),
            default => null,
        };

        if (! is_array($geometry)) {
            return;
        }

        $updateWidgetGeometry->update(auth()->user(), $widget, $geometry);
        $this->refreshComputedState();
    }

    public function toggleVisibility(int $widgetId, ToggleWidgetVisibility $toggleWidgetVisibility): void
    {
        $widget = $toggleWidgetVisibility->toggle(auth()->user(), $this->resolveWidget($widgetId));
        $this->refreshComputedState();

        if (! $widget->is_visible && $this->selectedWidgetId === $widget->id) {
            $this->selectedWidgetId = $widget->id;
        }
    }

    public function reorderWidget(int $widgetId, string $direction, ReorderWidgetInstance $reorderWidgetInstance): void
    {
        $widget = $reorderWidgetInstance->move(auth()->user(), $this->resolveWidget($widgetId), $direction);

        $this->selectedWidgetId = $widget->id;
        $this->refreshComputedState();
    }

    #[On('widget-created')]
    public function handleWidgetCreated(int $widgetId): void
    {
        $this->selectedWidgetId = $widgetId;
        $this->refreshComputedState();
        $this->syncPreviewSeeds();
    }

    #[On('widget-updated')]
    public function handleWidgetUpdated(): void
    {
        $this->refreshComputedState();
        $this->syncPreviewSeeds();
    }

    #[On('widget-deleted')]
    public function handleWidgetDeleted(?int $deletedWidgetId = null, ?int $selectedWidgetId = null): void
    {
        $this->selectedWidgetId = $this->resolvedSelectedWidgetId($selectedWidgetId, $deletedWidgetId);
        $this->refreshComputedState();
        $this->syncPreviewSeeds();
    }

    /**
     * @param  array<int, array<string, mixed>>  $widgets
     */
    public function restoreHistoryState(
        array $widgets,
        ?int $selectedWidgetId,
        RestoreCanvasWidgetState $restoreCanvasWidgetState,
    ): void {
        $canvas = $this->resolveCanvas();

        $restoreCanvasWidgetState->restore(auth()->user(), $canvas, $widgets);

        $this->selectedWidgetId = $this->resolvedSelectedWidgetId($selectedWidgetId);
        $this->refreshComputedState();
    }

    #[Computed]
    public function canvas(): Canvas
    {
        return $this->resolveCanvas(loadWidgets: true);
    }

    #[Computed]
    public function widgets(): Collection
    {
        return $this->canvas->orderedWidgetInstances;
    }

    #[Computed]
    public function selectedWidget(): ?WidgetInstance
    {
        return $this->widgets->firstWhere('id', $this->selectedWidgetId);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return auth()->user()?->can('update', $this->canvas) ?? false;
    }

    /**
     * @return array{
     *     selectedWidgetId:int|null,
     *     canEdit:bool,
     *     canvasWidth:int,
     *     canvasHeight:int,
     *     geometryLimits:array{
     *         minWidth:int,
     *         minHeight:int,
     *         maxContentWidth:int,
     *         maxContentHeight:int
     *     },
     *     widgets:array<int, array{
     *         id:int,
     *         name:string,
     *         sourceKind:string,
     *         sourceKindLabel:string,
     *         typeLabel:?string,
     *         isVisible:bool,
     *         previewStatus:string,
     *         previewMessage:?string,
     *         hasPreviewFailure:bool,
     *         positionX:int,
     *         positionY:int,
     *         width:int,
     *         height:int,
     *         contentWidth:int,
     *         contentHeight:int,
     *         cropTop:int,
     *         cropRight:int,
     *         cropBottom:int,
     *         cropLeft:int,
     *         zIndex:int,
     *         previewUrl:?string,
     *         previewToken:?string,
     *         previewMode:string
     *     }>
     * }
     */
    #[Computed]
    public function stageSnapshot(): array
    {
        $canvasWidth = max(1, (int) $this->canvas->width);
        $canvasHeight = max(1, (int) $this->canvas->height);

        return [
            'selectedWidgetId' => $this->selectedWidgetId,
            'canEdit' => $this->canEdit,
            'canvasWidth' => $canvasWidth,
            'canvasHeight' => $canvasHeight,
            'geometryLimits' => [
                'minWidth' => min(self::MIN_WIDTH, $canvasWidth),
                'minHeight' => min(self::MIN_HEIGHT, $canvasHeight),
                'maxContentWidth' => max(3840, $canvasWidth),
                'maxContentHeight' => max(2160, $canvasHeight),
            ],
            'widgets' => $this->widgets
                ->map(fn (WidgetInstance $widget): array => [
                        'id' => $widget->id,
                        'name' => $widget->displayName(),
                        'sourceKind' => $widget->source_kind->value,
                        'sourceKindLabel' => $widget->source_kind->label(),
                        'typeLabel' => $widget->type?->label(),
                        'isVisible' => $widget->is_visible,
                        'previewStatus' => $widget->preview_status->value,
                        'previewMessage' => $widget->preview_message,
                        'hasPreviewFailure' => $widget->hasPreviewFailure(),
                        'positionX' => $widget->position_x,
                        'positionY' => $widget->position_y,
                        'width' => $widget->width,
                        'height' => $widget->height,
                        'contentWidth' => $widget->content_width,
                        'contentHeight' => $widget->content_height,
                        'cropTop' => $widget->crop_top,
                        'cropRight' => $widget->crop_right,
                        'cropBottom' => $widget->crop_bottom,
                        'cropLeft' => $widget->crop_left,
                        'zIndex' => $widget->z_index,
                        'previewUrl' => $this->previewUrlFor($widget),
                        'previewToken' => $this->previewTokenFor($widget),
                        'previewMode' => $widget->source_kind === \App\Enums\Models\WidgetSourceKind::Proprietary
                            ? 'built_in'
                            : $widget->source_kind->value,
                    ])
                ->values()
                ->all(),
        ];
    }

    public function previewTokenFor(WidgetInstance $widget): ?string
    {
        return app(OverlayWidgetFrameUrlFactory::class)->editorToken(
            $widget,
            $this->previewSeeds[$widget->id] ?? [],
        );
    }

    public function previewUrlFor(WidgetInstance $widget): ?string
    {
        return app(OverlayWidgetFrameUrlFactory::class)->editorUrl(
            $widget,
            $this->previewSeeds[$widget->id] ?? [],
        );
    }

    public function render(): View
    {
        return view('livewire.canvases.canvas-composer');
    }

    protected function resolveCanvas(bool $loadWidgets = false): Canvas
    {
        $query = auth()->user()
            ->currentTeam
            ->canvases();

        if ($loadWidgets) {
            $query->with([
                'createdByUser',
                'orderedWidgetInstances.widget',
            ]);
        }

        return $query->findOrFail($this->canvasId);
    }

    protected function resolveWidget(int $widgetId): WidgetInstance
    {
        return $this->resolveCanvas()
            ->widgetInstances()
            ->findOrFail($widgetId);
    }

    protected function widgetExists(int $widgetId): bool
    {
        return $this->resolveCanvas()
            ->widgetInstances()
            ->whereKey($widgetId)
            ->exists();
    }

    private function syncPreviewSeeds(): void
    {
        $activeWidgetIds = [];

        foreach ($this->widgets as $widget) {
            $activeWidgetIds[] = $widget->id;

            if (
                $widget->type?->value === 'pomodoro'
                && ! isset(($widget->settings ?? [])['ends_at'], ($widget->settings ?? [])['remaining_seconds'])
                && ! isset($this->previewSeeds[$widget->id]['ends_at'])
            ) {
                $focusMinutes = (int) (($widget->settings ?? [])['focus_minutes'] ?? 25);

                $this->previewSeeds[$widget->id] = [
                    'ends_at' => now()->seconds(0)->addMinutes($focusMinutes)->toIso8601String(),
                ];
            }
        }

        $activeWidgetIds = array_fill_keys($activeWidgetIds, true);

        $this->previewSeeds = array_filter(
            $this->previewSeeds,
            static fn (int $widgetId): bool => isset($activeWidgetIds[$widgetId]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function refreshComputedState(): void
    {
        unset($this->canvas, $this->widgets, $this->selectedWidget, $this->canEdit, $this->stageSnapshot);
    }

    private function resolvedSelectedWidgetId(?int $preferredWidgetId = null, ?int $deletedWidgetId = null): ?int
    {
        if (is_int($preferredWidgetId) && $this->widgetExists($preferredWidgetId)) {
            return $preferredWidgetId;
        }

        if ($deletedWidgetId !== null) {
            return $this->resolveCanvas()
                ->orderedWidgetInstances()
                ->value('id');
        }

        if ($this->selectedWidgetId !== null && $this->widgetExists($this->selectedWidgetId)) {
            return $this->selectedWidgetId;
        }

        return $this->resolveCanvas()
            ->orderedWidgetInstances()
            ->value('id');
    }

    /**
     * @return array{
     *     position_x:int,
     *     position_y:int,
     *     width:int,
     *     height:int,
     *     content_width:int,
     *     content_height:int,
     *     crop_top:int,
     *     crop_right:int,
     *     crop_bottom:int,
     *     crop_left:int
     * }
     */
    private function geometryPayload(WidgetInstance $widget): array
    {
        return [
            'position_x' => $widget->position_x,
            'position_y' => $widget->position_y,
            'width' => $widget->width,
            'height' => $widget->height,
            'content_width' => $widget->content_width,
            'content_height' => $widget->content_height,
            'crop_top' => $widget->crop_top,
            'crop_right' => $widget->crop_right,
            'crop_bottom' => $widget->crop_bottom,
            'crop_left' => $widget->crop_left,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function cropResetGeometry(WidgetInstance $widget): array
    {
        return [
            ...$this->geometryPayload($widget),
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function sourceResetGeometry(WidgetInstance $widget): array
    {
        $defaults = $widget->editorDefaults();

        return [
            ...$this->geometryPayload($widget),
            'content_width' => $defaults['content_width'],
            'content_height' => $defaults['content_height'],
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function aspectResetGeometry(WidgetInstance $widget): array
    {
        $visibleWidth = max(1, $widget->visibleContentWidth());
        $visibleHeight = max(1, $widget->visibleContentHeight());
        $ratio = $visibleWidth / $visibleHeight;
        $minWidth = min(self::MIN_WIDTH, max(1, (int) $this->canvas->width));
        $minHeight = min(self::MIN_HEIGHT, max(1, (int) $this->canvas->height));
        $maxWidth = max($minWidth, (int) $this->canvas->width - $widget->position_x);
        $maxHeight = max($minHeight, (int) $this->canvas->height - $widget->position_y);
        $width = min($widget->width, $maxWidth);
        $height = max($minHeight, (int) round($width / $ratio));

        if ($height > $maxHeight) {
            $height = $maxHeight;
            $width = max($minWidth, (int) round($height * $ratio));
        }

        if ($width > $maxWidth) {
            $width = $maxWidth;
            $height = max($minHeight, (int) round($width / $ratio));
        }

        return [
            ...$this->geometryPayload($widget),
            'width' => $width,
            'height' => min($height, $maxHeight),
        ];
    }
}
