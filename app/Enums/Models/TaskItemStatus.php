<?php

namespace App\Enums\Models;

enum TaskItemStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Archived = 'archived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
