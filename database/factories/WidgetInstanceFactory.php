<?php

namespace Database\Factories;

use App\Enums\Models\WidgetPreviewStatus;
use App\Enums\Models\WidgetSourceKind;
use App\Enums\Models\WidgetType;
use App\Models\Canvas;
use App\Models\WidgetInstance;
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
            'team_id' => static fn (array $attributes): int => Canvas::query()
                ->findOrFail($attributes['canvas_id'])
                ->team_id,
            'source_kind' => WidgetSourceKind::BuiltIn,
            'type' => WidgetType::TaskList,
            'name' => fake()->words(2, true),
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

    public function pomodoro(): static
    {
        return $this->state(fn () => [
            'type' => WidgetType::Pomodoro,
            'name' => 'Pomodoro Timer',
        ]);
    }

    public function taskList(): static
    {
        return $this->state(fn () => [
            'type' => WidgetType::TaskList,
            'name' => 'Task List',
        ]);
    }

    public function remoteUrl(string $url = 'https://widgets.example.test/embed'): static
    {
        return $this->state(fn () => [
            'source_kind' => WidgetSourceKind::RemoteUrl,
            'type' => null,
            'name' => 'Remote Widget',
            'embed_url' => $url,
            'preview_status' => WidgetPreviewStatus::Ready,
            'preview_checked_at' => now(),
        ]);
    }
}
