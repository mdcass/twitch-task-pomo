<?php

namespace App\Enums\Models;

enum WidgetSourceKind: string
{
    case BuiltIn = 'built_in';
    case RemoteUrl = 'remote_url';

    public function label(): string
    {
        return match ($this) {
            self::BuiltIn => 'Built-in',
            self::RemoteUrl => 'Remote URL',
        };
    }
}
