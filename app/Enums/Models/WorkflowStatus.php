<?php

namespace App\Enums\Models;

enum WorkflowStatus: string
{
    case ERROR = 'error';
    case CLOSED = 'closed';
    case OPEN = 'open';

    public function display(): string
    {
        return ucfirst($this->value);
    }
}
