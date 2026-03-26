<?php

namespace App\Exceptions;

use LogicException;

class DomainInvariantViolation extends LogicException
{
    public static function for(string $message): self
    {
        return new self($message);
    }
}
