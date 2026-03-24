<?php

namespace App\Testing\Auth;

use App\Enums\ExternalAuthProvider;
use InvalidArgumentException;

final class TestingSocialAuthScenario
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(ExternalAuthProvider $provider, string $scenario): array
    {
        return match ($provider) {
            ExternalAuthProvider::Discord => self::discordScenario($scenario),
            ExternalAuthProvider::Spotify => self::spotifyScenario($scenario),
            ExternalAuthProvider::Twitch => self::twitchScenario($scenario),
        };
    }

    public static function sessionKey(ExternalAuthProvider $provider): string
    {
        return 'testing.oauth.scenario.'.$provider->value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function twitchScenario(string $scenario): array
    {
        return match ($scenario) {
            'existing-linked-login' => [
                'id' => 'testing-twitch-linked-user',
                'name' => 'Linked Streamer',
                'nickname' => 'linkedstreamer',
                'email' => 'linked-streamer@example.test',
                'avatar' => 'https://cdn.example.test/avatars/linked-streamer.png',
                'token' => 'testing-twitch-linked-token',
                'refreshToken' => 'testing-twitch-linked-refresh',
                'expiresIn' => 3600,
                'approvedScopes' => ['user:read:email'],
                'raw' => [
                    'id' => 'testing-twitch-linked-user',
                    'display_name' => 'Linked Streamer',
                    'login' => 'linkedstreamer',
                    'email' => 'linked-streamer@example.test',
                ],
            ],
            'existing-email' => [
                'id' => 'testing-twitch-existing-email',
                'name' => 'Existing Email Streamer',
                'nickname' => 'existingemail',
                'email' => 'existing-social@example.test',
                'avatar' => 'https://cdn.example.test/avatars/existing-email-streamer.png',
                'token' => 'testing-twitch-existing-token',
                'refreshToken' => 'testing-twitch-existing-refresh',
                'expiresIn' => 3600,
                'approvedScopes' => ['user:read:email'],
                'raw' => [
                    'id' => 'testing-twitch-existing-email',
                    'display_name' => 'Existing Email Streamer',
                    'login' => 'existingemail',
                    'email' => 'existing-social@example.test',
                ],
            ],
            'first-time-signup' => [
                'id' => 'testing-twitch-first-signup',
                'name' => 'Fresh Streamer',
                'nickname' => 'freshstreamer',
                'email' => 'fresh-streamer@example.test',
                'avatar' => 'https://cdn.example.test/avatars/fresh-streamer.png',
                'token' => 'testing-twitch-first-token',
                'refreshToken' => 'testing-twitch-first-refresh',
                'expiresIn' => 3600,
                'approvedScopes' => ['user:read:email'],
                'raw' => [
                    'id' => 'testing-twitch-first-signup',
                    'display_name' => 'Fresh Streamer',
                    'login' => 'freshstreamer',
                    'email' => 'fresh-streamer@example.test',
                ],
            ],
            'missing-email' => [
                'id' => 'testing-twitch-missing-email',
                'name' => 'Mystery Streamer',
                'nickname' => 'mysterystreamer',
                'email' => null,
                'avatar' => 'https://cdn.example.test/avatars/mystery-streamer.png',
                'token' => 'testing-twitch-missing-token',
                'refreshToken' => 'testing-twitch-missing-refresh',
                'expiresIn' => 3600,
                'approvedScopes' => ['user:read:email'],
                'raw' => [
                    'id' => 'testing-twitch-missing-email',
                    'display_name' => 'Mystery Streamer',
                    'login' => 'mysterystreamer',
                ],
            ],
            default => throw new InvalidArgumentException("Unknown testing OAuth scenario [{$scenario}] for Twitch."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function discordScenario(string $scenario): array
    {
        return match ($scenario) {
            'first-time-signup' => [
                'id' => 'testing-discord-first-signup',
                'name' => 'Discord Creator',
                'nickname' => 'discordcreator',
                'email' => 'discord-creator@example.test',
                'avatar' => 'https://cdn.example.test/avatars/discord-creator.png',
                'token' => 'testing-discord-first-token',
                'refreshToken' => 'testing-discord-first-refresh',
                'expiresIn' => 7200,
                'approvedScopes' => ['identify', 'email'],
                'raw' => [
                    'id' => 'testing-discord-first-signup',
                    'username' => 'Discord Creator',
                    'global_name' => 'Discord Creator',
                    'email' => 'discord-creator@example.test',
                ],
            ],
            default => throw new InvalidArgumentException("Unknown testing OAuth scenario [{$scenario}] for Discord."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function spotifyScenario(string $scenario): array
    {
        return match ($scenario) {
            'widget-link' => [
                'id' => 'testing-spotify-linked-user',
                'name' => 'Desk Mix',
                'nickname' => null,
                'email' => 'spotify-widget@example.test',
                'avatar' => 'https://cdn.example.test/avatars/spotify-widget.png',
                'token' => 'testing-spotify-access-token',
                'refreshToken' => 'testing-spotify-refresh-token',
                'expiresIn' => 3600,
                'approvedScopes' => ['user-read-email', 'user-read-currently-playing'],
                'raw' => [
                    'id' => 'testing-spotify-linked-user',
                    'display_name' => 'Desk Mix',
                    'email' => 'spotify-widget@example.test',
                    'images' => [
                        ['url' => 'https://cdn.example.test/avatars/spotify-widget.png'],
                    ],
                ],
            ],
            default => throw new InvalidArgumentException("Unknown testing OAuth scenario [{$scenario}] for Spotify."),
        };
    }
}
