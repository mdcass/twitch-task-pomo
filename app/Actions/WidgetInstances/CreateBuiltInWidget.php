<?php

namespace App\Actions\WidgetInstances;

use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Enums\Models\WidgetType;
use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;
use App\Support\Widgets\WidgetGeometryNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateBuiltInWidget
{
    public function __construct(
        private readonly WidgetGeometryNormalizer $geometryNormalizer,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function create(User $user, Canvas $canvas, WidgetType $type): WidgetInstance
    {
        Gate::forUser($user)->authorize('update', $canvas);

        $widget = DB::transaction(function () use ($canvas, $type): WidgetInstance {
            $nextIndex = (int) $canvas->widgetInstances()->max('z_index') + 1;
            $geometry = $this->geometryForCanvas($this->defaultGeometry($type, $nextIndex), $canvas);

            return $canvas->widgetInstances()->create([
                'team_id' => $canvas->team_id,
                'source_kind' => WidgetSourceKind::BuiltIn,
                'type' => $type,
                'name' => $type->defaultName(),
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
                'settings' => $this->settingsWithEditorDefaults($type->defaultSettings(), $geometry),
                'preview_status' => WidgetPreviewStatus::Ready,
                'preview_message' => null,
                'preview_checked_at' => now(),
            ]);
        });

        return $widget->fresh();
    }

    /**
     * @return array{position_x:int, position_y:int, width:int, height:int, content_width:int, content_height:int}
     */
    private function defaultGeometry(WidgetType $type, int $index): array
    {
        $positionOffsetX = min(($index - 1) * 32, 320);
        $positionOffsetY = min(($index - 1) * 24, 200);

        return match ($type) {
            WidgetType::TaskList => [
                'position_x' => 80 + $positionOffsetX,
                'position_y' => 80 + $positionOffsetY,
                'width' => 720,
                'height' => 560,
                'content_width' => 720,
                'content_height' => 560,
            ],
            WidgetType::Pomodoro => [
                'position_x' => 120 + $positionOffsetX,
                'position_y' => 120 + $positionOffsetY,
                'width' => 520,
                'height' => 320,
                'content_width' => 520,
                'content_height' => 320,
            ],
        };
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

    /**
     * @param  array<string, mixed>  $settings
     * @param  array{width:int, height:int, content_width:int, content_height:int}  $geometry
     * @return array<string, mixed>
     */
    private function settingsWithEditorDefaults(array $settings, array $geometry): array
    {
        $settings['editor_defaults'] = [
            'frame_width' => $geometry['width'],
            'frame_height' => $geometry['height'],
            'content_width' => $geometry['content_width'],
            'content_height' => $geometry['content_height'],
        ];

        return $settings;
    }
}
