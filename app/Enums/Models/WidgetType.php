<?php

namespace App\Enums\Models;

enum WidgetType: string
{
    case Pomodoro = 'pomodoro';
    case TaskList = 'task_list';

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
