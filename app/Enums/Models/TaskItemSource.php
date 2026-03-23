<?php

namespace App\Enums\Models;

enum TaskItemSource: string
{
    case Streamer = 'streamer';
    case TwitchChat = 'twitch_chat';
    case Web = 'web';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $source): string => $source->value,
            self::cases(),
        );
    }
}
