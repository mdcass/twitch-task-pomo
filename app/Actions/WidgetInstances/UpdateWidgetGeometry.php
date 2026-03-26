<?php

namespace App\Actions\WidgetInstances;

use App\Models\Canvas;
use App\Models\User;
use App\Models\WidgetInstance;
use App\Support\Widgets\WidgetGeometry;
use App\Support\Widgets\WidgetGeometryNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateWidgetGeometry
{
    public function __construct(
        private readonly WidgetGeometryNormalizer $geometryNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(User $user, WidgetInstance $widgetInstance, array $input): WidgetInstance
    {
        Gate::forUser($user)->authorize('update', $widgetInstance->canvas);

        $normalized = $this->normalizeForCanvas($widgetInstance->canvas, [
            'position_x' => $input['position_x'] ?? $widgetInstance->position_x,
            'position_y' => $input['position_y'] ?? $widgetInstance->position_y,
            'width' => $input['width'] ?? $widgetInstance->width,
            'height' => $input['height'] ?? $widgetInstance->height,
            'content_width' => $input['content_width'] ?? $widgetInstance->content_width,
            'content_height' => $input['content_height'] ?? $widgetInstance->content_height,
            'crop_top' => $input['crop_top'] ?? $widgetInstance->crop_top,
            'crop_right' => $input['crop_right'] ?? $widgetInstance->crop_right,
            'crop_bottom' => $input['crop_bottom'] ?? $widgetInstance->crop_bottom,
            'crop_left' => $input['crop_left'] ?? $widgetInstance->crop_left,
        ]);

        $widgetInstance->fill($normalized->toArray());
        $widgetInstance->save();

        return $widgetInstance->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @throws ValidationException
     */
    public function normalizeForCanvas(Canvas $canvas, array $input): WidgetGeometry
    {
        $limits = $this->geometryNormalizer->geometryLimits($canvas);
        $canvasWidth = max(1, (int) $canvas->width);
        $canvasHeight = max(1, (int) $canvas->height);

        $validated = Validator::make($input, [
            'position_x' => ['required', 'integer', 'min:0', 'max:'.max(0, $canvasWidth - 1)],
            'position_y' => ['required', 'integer', 'min:0', 'max:'.max(0, $canvasHeight - 1)],
            'width' => ['required', 'integer', 'min:'.$limits['minWidth'], 'max:'.$canvasWidth],
            'height' => ['required', 'integer', 'min:'.$limits['minHeight'], 'max:'.$canvasHeight],
            'content_width' => ['required', 'integer', 'min:1', 'max:'.$limits['maxContentWidth']],
            'content_height' => ['required', 'integer', 'min:1', 'max:'.$limits['maxContentHeight']],
            'crop_top' => ['required', 'integer', 'min:0'],
            'crop_right' => ['required', 'integer', 'min:0'],
            'crop_bottom' => ['required', 'integer', 'min:0'],
            'crop_left' => ['required', 'integer', 'min:0'],
        ])->validate();

        return $this->geometryNormalizer->normalize($canvas, WidgetGeometry::fromArray($validated));
    }
}
