<?php

namespace Database\Factories;

use App\Enums\Models\TaskItemSource;
use App\Enums\Models\TaskItemStatus;
use App\Models\StreamSession;
use App\Models\TaskItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskItem>
 */
class TaskItemFactory extends Factory
{
    protected $model = TaskItem::class;

    public function definition(): array
    {
        return [
            'stream_session_id' => StreamSession::factory(),
            'team_id' => static fn (array $attributes): int => StreamSession::query()
                ->findOrFail($attributes['stream_session_id'])
                ->team_id,
            'created_by_user_id' => null,
            'source' => TaskItemSource::TwitchChat,
            'submitted_by_username' => fake()->userName(),
            'submitted_by_provider_user_id' => fake()->numerify('viewer-########'),
            'body' => fake()->sentence(),
            'status' => TaskItemStatus::Pending,
            'sort_order' => 0,
            'completed_at' => null,
            'archived_at' => null,
            'metadata' => [],
        ];
    }

    public function streamer(): static
    {
        return $this->state(function (array $attributes): array {
            $streamSession = StreamSession::query()->findOrFail($attributes['stream_session_id']);
            $stream = $streamSession->stream()->firstOrFail();
            $providerAuth = $stream->providerAuth()->first();

            return [
                'created_by_user_id' => $providerAuth?->user_id,
                'source' => TaskItemSource::Streamer,
                'submitted_by_username' => $stream->channel_login,
                'submitted_by_provider_user_id' => $stream->provider_channel_id,
            ];
        });
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TaskItemStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => TaskItemStatus::Archived,
            'completed_at' => now()->subMinutes(5),
            'archived_at' => now(),
        ]);
    }
}
