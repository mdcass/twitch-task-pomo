<?php

namespace App\Enums\Models;

enum WidgetLifecycleState: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case PendingConnection = 'pending_connection';
    case Broken = 'broken';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::PendingConnection => 'Pending Connection',
            self::Broken => 'Broken',
            self::Archived => 'Archived',
        };
    }
}
