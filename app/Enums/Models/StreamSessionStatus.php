<?php

namespace App\Enums\Models;

enum StreamSessionStatus: string
{
    case Active = 'active';
    case Ended = 'ended';

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
