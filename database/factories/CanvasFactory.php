<?php

namespace Database\Factories;

use App\Models\Canvas;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Canvas>
 */
class CanvasFactory extends Factory
{
    protected $model = Canvas::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'created_by_user_id' => static fn (array $attributes): int => Team::query()
                ->findOrFail($attributes['team_id'])
                ->user_id,
            'uuid' => fake()->uuid(),
            'name' => fake()->words(3, true),
            'width' => 1920,
            'height' => 1080,
        ];
    }
}
