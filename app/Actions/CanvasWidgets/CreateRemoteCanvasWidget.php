<?php

namespace App\Actions\CanvasWidgets;

use App\Models\Canvas;
use App\Models\CanvasWidget;
use App\Models\User;
use App\Support\Widgets\RemoteWidgetPreviewInspector;
use App\Support\Widgets\RemoteWidgetUrlGuard;
use App\Support\Widgets\WidgetGeometryNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateRemoteCanvasWidget
{
    public function __construct(
        private readonly RemoteWidgetPreviewInspector $previewInspector,
        private readonly RemoteWidgetUrlGuard $remoteWidgetUrlGuard,
        private readonly WidgetGeometryNormalizer $geometryNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(User $user, Canvas $canvas, array $input): CanvasWidget
    {
        Gate::forUser($user)->authorize('update', $canvas);

        $validated = Validator::make($input, [
            'name' => ['nullable', 'string', 'max:255'],
            'embed_url' => [
                'required',
                'string',
                'max:2048',
                'url',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || parse_url($value, PHP_URL_SCHEME) !== 'https') {
                        $fail('The embed URL must be a valid HTTPS URL.');
                    }
                },
            ],
        ], [
            'embed_url.url' => 'The embed URL must be a valid HTTPS URL.',
        ])->validate();

        $validated['embed_url'] = $this->remoteWidgetUrlGuard->assertAllowed($validated['embed_url']);
        $previewResult = $this->previewInspector->inspect($validated['embed_url']);

        $placement = DB::transaction(function () use ($canvas, $previewResult, $validated): CanvasWidget {
            $nextIndex = (int) $canvas->canvasWidgets()->max('z_index') + 1;
            $geometry = $this->geometryForCanvas([
                'position_x' => min(120 + (($nextIndex - 1) * 36), 920),
                'position_y' => min(120 + (($nextIndex - 1) * 28), 520),
                'width' => 760,
                'height' => 480,
                'content_width' => 760,
                'content_height' => 480,
            ], $canvas);

            return $canvas->canvasWidgets()->create([
                'widget_id' => null,
                'team_id' => $canvas->team_id,
                'source_kind' => 'remote_url',
                'name' => trim((string) ($validated['name'] ?? '')) ?: null,
                'embed_url' => $validated['embed_url'],
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
                'preview_status' => $previewResult->status,
                'preview_message' => $previewResult->message,
                'preview_checked_at' => $previewResult->checkedAt,
            ]);
        });

        return $placement->fresh();
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
