<?php

namespace App\Exceptions;

use App\Workflows\GuardResult;
use Exception;

class WorkflowTransitionException extends Exception
{
    public function __construct(
        protected GuardResult $guardResult,
        protected string $transition,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        if ($message === '') {
            $message = "Cannot progress workflow transition '{$transition}': {$guardResult->message}";
        }

        parent::__construct($message, $code, $previous);
    }

    public function getGuardResult(): GuardResult
    {
        return $this->guardResult;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }
}
