<?php

namespace Database\Factories;

use App\Enums\TeamType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company().' Streamer Profile',
            'user_id' => User::factory(),
            'type' => TeamType::Streamer,
        ];
    }

    public function streamer(): static
    {
        return $this->state(fn () => [
            'type' => TeamType::Streamer,
            'name' => $this->faker->unique()->company().' Streamer Profile',
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn () => [
            'type' => TeamType::Viewer,
            'name' => $this->faker->unique()->company().' Viewer Profile',
        ]);
    }
}
