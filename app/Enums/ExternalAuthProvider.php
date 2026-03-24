<?php

namespace App\Enums;

enum ExternalAuthProvider: string
{
    case Discord = 'discord';
    case Spotify = 'spotify';
    case Twitch = 'twitch';

    public function label(): string
    {
        return match ($this) {
            self::Discord => 'Discord',
            self::Spotify => 'Spotify',
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
            self::Spotify => ['user-read-email', 'user-read-currently-playing'],
            self::Twitch => ['user:read:email'],
        };
    }

    public function supportsGuestAuth(): bool
    {
        return match ($this) {
            self::Discord, self::Twitch => true,
            self::Spotify => false,
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
