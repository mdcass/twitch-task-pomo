<?php

namespace App\Enums;

enum TeamMemberRole: string
{
    case Moderator = 'moderator';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Moderator => 'Moderator',
        };
    }
}
