<?php

namespace Database\Factories;

use App\Enums\Models\PomodoroSessionState;
use App\Models\PomodoroSession;
use App\Models\StreamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PomodoroSession>
 */
class PomodoroSessionFactory extends Factory
{
    protected $model = PomodoroSession::class;

    public function definition(): array
    {
        $startedAt = now()->subMinutes(5);
        $focusMinutes = 25;
        $breakMinutes = 5;

        return [
            'stream_session_id' => StreamSession::factory(),
            'team_id' => static fn (array $attributes): int => StreamSession::query()
                ->findOrFail($attributes['stream_session_id'])
                ->team_id,
            'state' => PomodoroSessionState::Focus,
            'focus_minutes' => $focusMinutes,
            'break_minutes' => $breakMinutes,
            'started_at' => $startedAt,
            'ends_at' => (clone $startedAt)->addMinutes($focusMinutes),
            'paused_at' => null,
            'sequence' => 1,
            'metadata' => [],
        ];
    }

    public function onBreak(): static
    {
        return $this->state(function (array $attributes): array {
            $startedAt = now()->subMinutes(2);
            $breakMinutes = (int) ($attributes['break_minutes'] ?? 5);

            return [
                'state' => PomodoroSessionState::Break,
                'started_at' => $startedAt,
                'ends_at' => (clone $startedAt)->addMinutes($breakMinutes),
                'paused_at' => null,
            ];
        });
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'state' => PomodoroSessionState::Paused,
            'paused_at' => now(),
        ]);
    }

    public function completed(): static
    {
        $endedAt = now()->subMinute();

        return $this->state(fn () => [
            'state' => PomodoroSessionState::Completed,
            'started_at' => $endedAt->copy()->subMinutes(25),
            'ends_at' => $endedAt,
            'paused_at' => null,
        ]);
    }
}
