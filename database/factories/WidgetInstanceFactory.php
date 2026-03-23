<?php

namespace Database\Factories;

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
            'type' => WidgetType::TaskList,
            'name' => fake()->words(2, true),
            'position_x' => 0,
            'position_y' => 0,
            'width' => 640,
            'height' => 360,
            'z_index' => 0,
            'is_visible' => true,
            'settings' => [],
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
}
