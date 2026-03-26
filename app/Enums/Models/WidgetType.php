<?php

namespace App\Enums\Models;

use App\Support\Widgets\Definitions\FollowerGoalWidgetDefinition;
use App\Support\Widgets\Definitions\PomodoroWidgetDefinition;
use App\Support\Widgets\Definitions\SpotifyNowPlayingWidgetDefinition;
use App\Support\Widgets\Definitions\TaskListWidgetDefinition;
use App\Support\Widgets\WidgetDefinition;

enum WidgetType: string
{
    case Pomodoro = 'pomodoro';
    case TaskList = 'task_list';
    case FollowerGoal = 'follower_goal';
    case SpotifyNowPlaying = 'spotify_now_playing';

    public function label(): string
    {
        return match ($this) {
            self::Pomodoro => 'Pomodoro Timer',
            self::TaskList => 'Task List',
            self::FollowerGoal => 'Follower Goal',
            self::SpotifyNowPlaying => 'Spotify Now Playing',
        };
    }

    public function defaultName(): string
    {
        return $this->label();
    }

    public function definition(): WidgetDefinition
    {
        return app($this->definitionClass());
    }

    /**
     * @return class-string<WidgetDefinition>
     */
    private function definitionClass(): string
    {
        return match ($this) {
            self::Pomodoro => PomodoroWidgetDefinition::class,
            self::TaskList => TaskListWidgetDefinition::class,
            self::FollowerGoal => FollowerGoalWidgetDefinition::class,
            self::SpotifyNowPlaying => SpotifyNowPlayingWidgetDefinition::class,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
