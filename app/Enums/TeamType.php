<?php

namespace App\Enums;

enum TeamType: string
{
    case Viewer = 'viewer';
    case Streamer = 'streamer';

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

    public function label(): string
    {
        return match ($this) {
            self::Viewer => 'Viewer',
            self::Streamer => 'Streamer',
        };
    }
}
