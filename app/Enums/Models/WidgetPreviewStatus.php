<?php

namespace App\Enums\Models;

enum WidgetPreviewStatus: string
{
    case Ready = 'ready';
    case Blocked = 'blocked';
    case Unknown = 'unknown';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready',
            self::Blocked => 'Blocked',
            self::Unknown => 'Unknown',
            self::Pending => 'Checking',
        };
    }
}
