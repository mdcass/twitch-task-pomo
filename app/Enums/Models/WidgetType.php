<?php

namespace App\Enums\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

enum WidgetType: string
{
    case Pomodoro = 'pomodoro';
    case TaskList = 'task_list';

    public function label(): string
    {
        return match ($this) {
            self::Pomodoro => 'Pomodoro Timer',
            self::TaskList => 'Task List',
        };
    }

    public function defaultName(): string
    {
        return $this->label();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultSettings(): array
    {
        return match ($this) {
            self::TaskList => [
                'title' => 'Focus Queue',
                'pending' => ['Plan stream outline', 'Refine camera framing'],
                'completed' => ['Warm up intro scene'],
            ],
            self::Pomodoro => [
                'title' => 'Deep Work Sprint',
                'state' => 'focus',
                'focus_minutes' => 25,
                'break_minutes' => 5,
            ],
        };
    }

    public function previewUrl(array $settings = []): ?string
    {
        return match ($this) {
            self::TaskList => $this->taskListPreviewUrl($settings),
            self::Pomodoro => $this->pomodoroPreviewUrl($settings),
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

    private function taskListPreviewUrl(array $settings): ?string
    {
        if (! Route::has('local.widgets.task-list')) {
            return null;
        }

        return route('local.widgets.task-list', [
            'title' => $settings['title'] ?? 'Focus Queue',
            'pending' => $settings['pending'] ?? ['Plan stream outline', 'Refine camera framing'],
            'completed' => $settings['completed'] ?? ['Warm up intro scene'],
        ], false);
    }

    private function pomodoroPreviewUrl(array $settings): ?string
    {
        if (! Route::has('local.widgets.pomodoro')) {
            return null;
        }

        return route('local.widgets.pomodoro', [
            'title' => $settings['title'] ?? 'Deep Work Sprint',
            'state' => $settings['state'] ?? 'focus',
            'focus_minutes' => $settings['focus_minutes'] ?? 25,
            'break_minutes' => $settings['break_minutes'] ?? 5,
            'ends_at' => $settings['ends_at'] ?? Carbon::now()->seconds(0)->addMinutes(25)->toIso8601String(),
            'remaining_seconds' => $settings['remaining_seconds'] ?? null,
        ], false);
    }
}
