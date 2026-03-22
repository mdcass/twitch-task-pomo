<?php

namespace App\Enums;

enum ExternalAuthProvider: string
{
    case Discord = 'discord';
    case Twitch = 'twitch';

    public function label(): string
    {
        return match ($this) {
            self::Discord => 'Discord',
            self::Twitch => 'Twitch',
        };
    }

    /**
     * @return list<string>
     */
    public function authScopes(): array
    {
        return match ($this) {
            self::Discord => ['identify', 'email'],
            self::Twitch => ['user:read:email'],
        };
    }

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
