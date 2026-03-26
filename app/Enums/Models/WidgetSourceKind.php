<?php

namespace App\Enums\Models;

enum WidgetSourceKind: string
{
    case Proprietary = 'proprietary';
    case RemoteUrl = 'remote_url';

    public function label(): string
    {
        return match ($this) {
            self::Proprietary => 'Proprietary',
            self::RemoteUrl => 'Remote URL',
        };
    }
}
