<?php

namespace Database\Factories;

use App\Enums\Models\StreamSessionStatus;
use App\Models\Stream;
use App\Models\StreamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StreamSession>
 */
class StreamSessionFactory extends Factory
{
    protected $model = StreamSession::class;

    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(1, 15));

        return [
            'stream_id' => Stream::factory(),
            'team_id' => static fn (array $attributes): int => Stream::query()
                ->findOrFail($attributes['stream_id'])
                ->team_id,
            'status' => StreamSessionStatus::Active,
            'is_test' => false,
            'started_at' => $startedAt,
            'ended_at' => null,
            'metadata' => [],
        ];
    }

    public function test(): static
    {
        return $this->state(fn () => [
            'is_test' => true,
        ]);
    }

    public function ended(): static
    {
        return $this->state(function (): array {
            $startedAt = now()->subMinutes(fake()->numberBetween(30, 90));
            $endedAt = (clone $startedAt)->addMinutes(fake()->numberBetween(25, 60));

            return [
                'status' => StreamSessionStatus::Ended,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ];
        });
    }
}
