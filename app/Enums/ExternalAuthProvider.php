<?php

namespace App\Enums;

enum ExternalAuthProvider: string
{
    case Discord = 'discord';
    case Twitch = 'twitch';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $provider): string => $provider->value,
            self::cases(),
        );
    }
}
