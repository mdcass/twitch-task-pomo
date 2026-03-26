<?php

namespace App\Actions\CanvasWidgets;

use App\Models\Canvas;
use App\Models\CanvasWidget;
use App\Models\User;
use App\Models\Widget;
use App\Models\WidgetInstance;
use App\Support\Widgets\WidgetDefinitionRegistry;
use App\Support\Widgets\WidgetGeometryNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AttachWidgetToCanvas
{
    public function __construct(
        private readonly WidgetDefinitionRegistry $widgetDefinitions,
        private readonly WidgetGeometryNormalizer $geometryNormalizer,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function attach(User $user, Canvas $canvas, Widget $widget): WidgetInstance
    {
        Gate::forUser($user)->authorize('update', $canvas);

        abort_unless($canvas->team_id === $widget->team_id, 404);

        $definition = $this->widgetDefinitions->forType($widget->type);

        $placement = DB::transaction(function () use ($canvas, $definition, $widget): WidgetInstance {
            $nextIndex = (int) $canvas->widgetInstances()->max('z_index') + 1;
            $artboard = $definition->artboard();
            $geometry = $this->geometryForCanvas([
                'position_x' => min(80 + (($nextIndex - 1) * 32), max(0, $canvas->width - 120)),
                'position_y' => min(80 + (($nextIndex - 1) * 24), max(0, $canvas->height - 90)),
                'width' => $artboard['width'],
                'height' => $artboard['height'],
                'content_width' => $artboard['width'],
                'content_height' => $artboard['height'],
            ], $canvas);

            return $canvas->widgetInstances()->create([
                'widget_id' => $widget->id,
                'team_id' => $canvas->team_id,
                'source_kind' => 'proprietary',
                'position_x' => $geometry['position_x'],
                'position_y' => $geometry['position_y'],
                'width' => $geometry['width'],
                'height' => $geometry['height'],
                'content_width' => $geometry['content_width'],
                'content_height' => $geometry['content_height'],
                'crop_top' => 0,
                'crop_right' => 0,
                'crop_bottom' => 0,
                'crop_left' => 0,
                'z_index' => $nextIndex,
                'is_visible' => true,
                'settings' => [
                    'editor_defaults' => [
                        'frame_width' => $geometry['width'],
                        'frame_height' => $geometry['height'],
                        'content_width' => $geometry['content_width'],
                        'content_height' => $geometry['content_height'],
                    ],
                ],
                'preview_status' => 'ready',
                'preview_message' => null,
                'preview_checked_at' => now(),
            ]);
        });

        return $placement->fresh(['widget']);
    }

    /**
     * @param  array{position_x:int, position_y:int, width:int, height:int, content_width:int, content_height:int}  $geometry
     * @return array{position_x:int, position_y:int, width:int, height:int, content_width:int, content_height:int, crop_top:int, crop_right:int, crop_bottom:int, crop_left:int}
     */
    private function geometryForCanvas(array $geometry, Canvas $canvas): array
    {
        return $this->geometryNormalizer->normalize($canvas, [
            ...$geometry,
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
        ]);
    }
}
