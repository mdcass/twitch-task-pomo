<?php

namespace App\Enums\Models;

enum PomodoroSessionState: string
{
    case Focus = 'focus';
    case Break = 'break';
    case Paused = 'paused';
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $state): string => $state->value,
            self::cases(),
        );
    }
}
