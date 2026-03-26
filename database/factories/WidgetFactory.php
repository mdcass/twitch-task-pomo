<?php

namespace Database\Factories;

use App\Enums\Models\WidgetLifecycleState;
use App\Enums\Models\WidgetType;
use App\Models\Team;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Widget>
 */
class WidgetFactory extends Factory
{
    protected $model = Widget::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'team_id' => Team::factory(),
            'created_by_user_id' => User::factory(),
            'type' => WidgetType::TaskList,
            'name' => 'Task List',
            'schema_version' => 1,
            'config' => [
                'title' => 'Focus Queue',
                'pending' => ['Plan stream outline'],
                'completed' => [],
            ],
            'appearance' => [
                'variant' => 'default',
            ],
            'lifecycle_state' => WidgetLifecycleState::Ready,
            'published_at' => null,
            'publication_key' => null,
        ];
    }

    public function forTeam(Team $team, ?User $user = null): static
    {
        $owner = $user ?? $team->owner;

        return $this->state(fn () => [
            'team_id' => $team->id,
            'created_by_user_id' => $owner?->id ?? User::factory(),
        ]);
    }

    public function pomodoro(): static
    {
        return $this->state(fn () => [
            'type' => WidgetType::Pomodoro,
            'name' => 'Pomodoro Timer',
            'config' => [
                'title' => 'Deep Work Sprint',
                'focus_minutes' => 25,
                'break_minutes' => 5,
            ],
        ]);
    }

    public function taskList(): static
    {
        return $this->state(fn () => [
            'type' => WidgetType::TaskList,
            'name' => 'Task List',
        ]);
    }

    public function followerGoal(): static
    {
        return $this->pendingConnection()->state(fn () => [
            'type' => WidgetType::FollowerGoal,
            'name' => 'Follower Goal',
            'config' => [
                'title' => 'Road to Partner',
                'goal_target' => 50,
                'end_date' => now()->addMonth()->toDateString(),
                'sound_preset' => 'chime',
            ],
        ]);
    }

    public function spotifyNowPlaying(): static
    {
        return $this->pendingConnection()->state(fn () => [
            'type' => WidgetType::SpotifyNowPlaying,
            'name' => 'Spotify Now Playing',
            'config' => [
                'title' => 'Now Playing',
                'show_album_art' => true,
            ],
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'lifecycle_state' => WidgetLifecycleState::Archived,
        ]);
    }

    public function broken(): static
    {
        return $this->state(fn () => [
            'lifecycle_state' => WidgetLifecycleState::Broken,
        ]);
    }

    public function pendingConnection(): static
    {
        return $this->state(fn () => [
            'lifecycle_state' => WidgetLifecycleState::PendingConnection,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'lifecycle_state' => WidgetLifecycleState::Ready,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'published_at' => now(),
            'publication_key' => (string) Str::uuid(),
        ]);
    }
}
