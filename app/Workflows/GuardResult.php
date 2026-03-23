<?php

namespace App\Workflows;

class GuardResult
{
    public function __construct(
        public bool $allowed,
        public ?string $message = null,
    ) {}

    public static function allowed(): static
    {
        return new static(true);
    }

    public static function blocked(string $message): static
    {
        return new static(false, $message);
    }

    public static function invalid(string $message): static
    {
        return static::blocked($message);
    }

    public function passes(): bool
    {
        return $this->allowed;
    }
}
