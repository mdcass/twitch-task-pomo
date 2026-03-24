<?php

namespace App\Actions\Canvases;

use App\Actions\Canvases\Concerns\ValidatesCanvasAttributes;
use App\Actions\WidgetInstances\UpdateWidgetGeometry;
use App\Models\Activity;
use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateCanvas
{
    use ValidatesCanvasAttributes;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     */
    public function update(User $user, Canvas $canvas, array $input): Canvas
    {
        Gate::forUser($user)->authorize('update', $canvas);

        $validated = $this->validateCanvasAttributes($input);
        $originalWidth = (int) $canvas->width;
        $originalHeight = (int) $canvas->height;
        $canvas->fill($validated);

        if (! $canvas->isDirty(['name', 'width', 'height'])) {
            return $canvas;
        }

        $targetWidth = (int) $canvas->width;
        $targetHeight = (int) $canvas->height;

        Activity::withCauser($user, function () use ($canvas, $originalWidth, $originalHeight, $targetWidth, $targetHeight): bool {
            return DB::transaction(function () use ($canvas, $originalWidth, $originalHeight, $targetWidth, $targetHeight): bool {
                if ($originalWidth !== $targetWidth || $originalHeight !== $targetHeight) {
                    $this->rescaleWidgets($canvas, $originalWidth, $originalHeight);
                }

                return $canvas->save();
            });
        });

        return $canvas->fresh();
    }

    private function rescaleWidgets(Canvas $canvas, int $originalWidth, int $originalHeight): void
    {
        $scale = min(
            (int) $canvas->width / max(1, $originalWidth),
            (int) $canvas->height / max(1, $originalHeight),
        );
        $geometryUpdater = app(UpdateWidgetGeometry::class);

        $canvas->widgetInstances()->get()->each(function (WidgetInstance $widget) use ($canvas, $geometryUpdater, $scale): void {
            $normalized = $geometryUpdater->normalizeForCanvas($canvas, [
                'position_x' => $this->scaleValue($widget->position_x, $scale),
                'position_y' => $this->scaleValue($widget->position_y, $scale),
                'width' => $this->scaleValue($widget->width, $scale),
                'height' => $this->scaleValue($widget->height, $scale),
                'content_width' => $this->scaleValue($widget->content_width, $scale),
                'content_height' => $this->scaleValue($widget->content_height, $scale),
                'crop_top' => $this->scaleValue($widget->crop_top, $scale),
                'crop_right' => $this->scaleValue($widget->crop_right, $scale),
                'crop_bottom' => $this->scaleValue($widget->crop_bottom, $scale),
                'crop_left' => $this->scaleValue($widget->crop_left, $scale),
            ]);

            $widget->fill($normalized);
            $widget->settings = $this->scaledEditorDefaults(
                $widget->settings ?? [],
                $scale,
                $canvas,
                $geometryUpdater,
            );
            $widget->save();
        });
    }

    private function scaleValue(int $value, float $scale): int
    {
        return (int) round($value * $scale);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function scaledEditorDefaults(
        array $settings,
        float $scale,
        Canvas $canvas,
        UpdateWidgetGeometry $geometryUpdater,
    ): array {
        $defaults = data_get($settings, 'editor_defaults');

        if (! is_array($defaults)) {
            return $settings;
        }

        $normalized = $geometryUpdater->normalizeForCanvas($canvas, [
            'position_x' => 0,
            'position_y' => 0,
            'width' => $this->scaleValue((int) ($defaults['frame_width'] ?? 0), $scale),
            'height' => $this->scaleValue((int) ($defaults['frame_height'] ?? 0), $scale),
            'content_width' => $this->scaleValue((int) ($defaults['content_width'] ?? 0), $scale),
            'content_height' => $this->scaleValue((int) ($defaults['content_height'] ?? 0), $scale),
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
        ]);

        Arr::set($settings, 'editor_defaults.frame_width', $normalized['width']);
        Arr::set($settings, 'editor_defaults.frame_height', $normalized['height']);
        Arr::set($settings, 'editor_defaults.content_width', $normalized['content_width']);
        Arr::set($settings, 'editor_defaults.content_height', $normalized['content_height']);

        return $settings;
    }
}
