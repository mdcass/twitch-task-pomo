<?php

namespace App\Enums;

enum UserSettingKey: string
{
    case LegalAcceptanceHistory = 'legal_acceptance_history';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $key): string => $key->value,
            self::cases(),
        );
    }
}
