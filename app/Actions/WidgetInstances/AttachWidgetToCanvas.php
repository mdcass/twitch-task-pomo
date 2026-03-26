<?php

namespace App\Actions\WidgetInstances;

use App\Enums\Models\WidgetPreviewStatus;
use App\Models\Canvas;
use App\Models\User;
use App\Models\Widget;
use App\Models\WidgetInstance;
use App\Support\Widgets\WidgetGeometry;
use App\Support\Widgets\WidgetInstanceSpec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class AttachWidgetToCanvas
{
    public function __construct(
        private readonly CreateWidgetInstance $createWidgetInstance,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function attach(User $user, Canvas $canvas, Widget $widget): WidgetInstance
    {
        Gate::forUser($user)->authorize('attachWidget', [$canvas, $widget]);

        $definition = $widget->definition();
        $artboard = $definition->artboard();

        return $this->createWidgetInstance->create($canvas, new WidgetInstanceSpec(
            sourceKind: \App\Enums\Models\WidgetSourceKind::Proprietary,
            widgetId: $widget->id,
            geometry: $this->defaultGeometryFor(
                canvas: $canvas,
                width: (int) $artboard['width'],
                height: (int) $artboard['height'],
            ),
            previewStatus: WidgetPreviewStatus::Ready,
            previewCheckedAt: now(),
        ));
    }

    private function defaultGeometryFor(Canvas $canvas, int $width, int $height): WidgetGeometry
    {
        $placementIndex = (int) $canvas->widgetInstances()->max('z_index') + 1;

        return WidgetGeometry::uncropped(
            positionX: min(
                WidgetGeometry::DEFAULT_ATTACHED_WIDGET_BASE_X + (($placementIndex - 1) * WidgetGeometry::DEFAULT_ATTACHED_WIDGET_STEP_X),
                max(0, $canvas->width - WidgetGeometry::DEFAULT_ATTACHED_WIDGET_MAX_X_PADDING),
            ),
            positionY: min(
                WidgetGeometry::DEFAULT_ATTACHED_WIDGET_BASE_Y + (($placementIndex - 1) * WidgetGeometry::DEFAULT_ATTACHED_WIDGET_STEP_Y),
                max(0, $canvas->height - WidgetGeometry::DEFAULT_ATTACHED_WIDGET_MAX_Y_PADDING),
            ),
            width: $width,
            height: $height,
            contentWidth: $width,
            contentHeight: $height,
        );
    }
}
