<?php

namespace Database\Factories;

use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Models\Canvas;
use App\Models\WidgetInstance;
use App\Models\Widget;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WidgetInstance>
 */
class WidgetInstanceFactory extends Factory
{
    protected $model = WidgetInstance::class;

    public function definition(): array
    {
        return [
            'canvas_id' => Canvas::factory(),
            'widget_id' => static fn (array $attributes): int => self::createWidgetIdForCanvas($attributes['canvas_id'], $attributes),
            'team_id' => static fn (array $attributes): int => Canvas::query()
                ->findOrFail(self::resolveCanvasId($attributes['canvas_id'], $attributes))
                ->team_id,
            'source_kind' => WidgetSourceKind::Proprietary,
            'name' => null,
            'embed_url' => null,
            'position_x' => 0,
            'position_y' => 0,
            'width' => 640,
            'height' => 360,
            'content_width' => 640,
            'content_height' => 360,
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'crop_left' => 0,
            'z_index' => 0,
            'is_visible' => true,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 640,
                    'frame_height' => 360,
                    'content_width' => 640,
                    'content_height' => 360,
                ],
            ],
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_message' => null,
            'preview_checked_at' => now(),
        ];
    }

    public function remoteUrl(string $url = 'https://widgets.example.test/embed'): static
    {
        return $this->state(fn () => [
            'widget_id' => null,
            'source_kind' => WidgetSourceKind::RemoteUrl,
            'name' => 'Remote Widget',
            'embed_url' => $url,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_checked_at' => now(),
        ]);
    }

    public function withoutBackingWidget(): static
    {
        return $this->state(fn () => [
            'widget_id' => null,
            'source_kind' => WidgetSourceKind::Proprietary,
        ]);
    }

    public function pomodoro(): static
    {
        return $this->state(fn (array $attributes) => [
            'widget_id' => self::createWidgetIdForCanvas($attributes['canvas_id'], $attributes, static fn (WidgetFactory $factory) => $factory->pomodoro()),
            'source_kind' => WidgetSourceKind::Proprietary,
            'name' => null,
            'embed_url' => null,
            'width' => 520,
            'height' => 320,
            'content_width' => 520,
            'content_height' => 320,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 520,
                    'frame_height' => 320,
                    'content_width' => 520,
                    'content_height' => 320,
                ],
            ],
        ]);
    }

    public function taskList(): static
    {
        return $this->state(fn (array $attributes) => [
            'widget_id' => self::createWidgetIdForCanvas($attributes['canvas_id'], $attributes, static fn (WidgetFactory $factory) => $factory->taskList()),
            'source_kind' => WidgetSourceKind::Proprietary,
            'name' => null,
            'embed_url' => null,
            'width' => 720,
            'height' => 560,
            'content_width' => 720,
            'content_height' => 560,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 720,
                    'frame_height' => 560,
                    'content_width' => 720,
                    'content_height' => 560,
                ],
            ],
        ]);
    }

    public function followerGoal(): static
    {
        return $this->state(fn (array $attributes) => [
            'widget_id' => self::createWidgetIdForCanvas($attributes['canvas_id'], $attributes, static fn (WidgetFactory $factory) => $factory->followerGoal()),
            'source_kind' => WidgetSourceKind::Proprietary,
            'name' => null,
            'embed_url' => null,
            'width' => 520,
            'height' => 220,
            'content_width' => 520,
            'content_height' => 220,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 520,
                    'frame_height' => 220,
                    'content_width' => 520,
                    'content_height' => 220,
                ],
            ],
        ]);
    }

    public function spotifyNowPlaying(): static
    {
        return $this->state(fn (array $attributes) => [
            'widget_id' => self::createWidgetIdForCanvas($attributes['canvas_id'], $attributes, static fn (WidgetFactory $factory) => $factory->spotifyNowPlaying()),
            'source_kind' => WidgetSourceKind::Proprietary,
            'name' => null,
            'embed_url' => null,
            'width' => 720,
            'height' => 240,
            'content_width' => 720,
            'content_height' => 240,
            'settings' => [
                'editor_defaults' => [
                    'frame_width' => 720,
                    'frame_height' => 240,
                    'content_width' => 720,
                    'content_height' => 240,
                ],
            ],
        ]);
    }

    private static function createWidgetIdForCanvas(mixed $canvasId, array $attributes, ?callable $configure = null): int
    {
        $canvas = Canvas::query()->with('team.owner')->findOrFail(self::resolveCanvasId($canvasId, $attributes));
        $factory = Widget::factory()->forTeam($canvas->team, $canvas->team->owner);
        $factory = $configure ? $configure($factory) : $factory;

        return $factory->create()->id;
    }

    private static function resolveCanvasId(mixed $canvasId, array $attributes): int
    {
        while ($canvasId instanceof Closure) {
            $canvasId = $canvasId($attributes);
        }

        if ($canvasId instanceof Canvas) {
            return $canvasId->getKey();
        }

        return (int) $canvasId;
    }
}
